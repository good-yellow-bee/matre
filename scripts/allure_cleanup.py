#!/usr/bin/env python3
"""
Allure Test History Cleanup Script (v2)

Removes test execution history from Allure reports with advanced filtering.
Supports multi-environment batch operations and suite-based filtering.

Usage Examples:
    # Remove test from all environments
    python allure_cleanup.py -t MOEC8899ES --env all

    # Remove from specific suite only (e.g., group1)
    python allure_cleanup.py -t MOEC2417 --in-suite group1 --env all

    # Remove from ES environments only
    python allure_cleanup.py -t MOEC3758 --env es

    # Remove from group1 on US environments only
    python allure_cleanup.py -t MOEC-5173 --in-suite group1 --env us

    # Keep entries in functional\\us suite (remove from everywhere else)
    python allure_cleanup.py -t MOEC5317 --not-in-suite us --env all

    # Multiple test IDs
    python allure_cleanup.py -t MOEC3758 -t MOEC11676 --env es

    # Dry run
    python allure_cleanup.py -t MOEC2417 --in-suite group1 --env all --dry-run

Environment shortcuts:
    all     = stage-us, stage-es, preprod-us, preprod-es
    us      = stage-us, preprod-us
    es      = stage-es, preprod-es
    stage   = stage-us, stage-es
    preprod = preprod-us, preprod-es
"""

import argparse
import json
import subprocess
import sys
import tempfile
import os
from pathlib import Path
from typing import Optional


# Environment shortcuts
ENV_SHORTCUTS = {
    'all': ['stage-us', 'stage-es', 'preprod-us', 'preprod-es'],
    'us': ['stage-us', 'preprod-us'],
    'es': ['stage-es', 'preprod-es'],
    'stage': ['stage-us', 'stage-es'],
    'preprod': ['preprod-us', 'preprod-es'],
}

# Default base path template
DEFAULT_BASE_PATH = '/home/ubuntu/ABBTests/src/pub/allure-report-{env}'
DEFAULT_SSH_HOST = 'abb'


def expand_environments(env_arg: str) -> list[str]:
    """Expand environment argument to list of environments."""
    if env_arg in ENV_SHORTCUTS:
        return ENV_SHORTCUTS[env_arg]
    return [e.strip() for e in env_arg.split(',')]


def generate_cleanup_script(
    test_ids: list[str],
    in_suites: Optional[list[str]] = None,
    not_in_suites: Optional[list[str]] = None,
    dry_run: bool = False,
    strict: bool = False
) -> str:
    """Generate Python cleanup script to run on remote."""

    # Build filter conditions
    suite_conditions = []
    if in_suites:
        suite_checks = ' or '.join([f'"{s}" in suite' for s in in_suites])
        suite_conditions.append(f'({suite_checks})')
    if not_in_suites:
        suite_checks = ' and '.join([f'"{s}" not in suite' for s in not_in_suites])
        suite_conditions.append(f'({suite_checks})')

    suite_filter = ' and '.join(suite_conditions) if suite_conditions else 'True'

    # Escape test IDs for Python string
    test_ids_str = ', '.join([f'"{tid}"' for tid in test_ids])

    # Serialize suite filters as Python lists
    in_suites_str = ', '.join([f'"{s}"' for s in (in_suites or [])])
    not_in_suites_str = ', '.join([f'"{s}"' for s in (not_in_suites or [])])

    script = f'''
import json
import os
import re
import sys
import xml.etree.ElementTree as ET

def matches_test_id(name, test_ids, strict=False):
    """Check if name matches any test ID. If strict, use word boundary matching."""
    for tid in test_ids:
        if strict:
            # Word boundary match - tid must be a complete word in name
            if re.search(rf'\\b{{re.escape(tid)}}\\b', name):
                return True
        else:
            # Substring match
            if tid in name:
                return True
    return False

def cleanup_source_results(base_path, test_ids, in_suites, not_in_suites, strict=False, dry_run=False):
    """Clean source results (XML testsuite files) to prevent re-appearance after report regeneration."""
    # Derive source path from report path
    # /home/ubuntu/ABBTests/src/pub/allure-report-stage-us → /home/ubuntu/ABBTests/src/dev/tests/acceptance/_output/allure-results-stage-us
    source_dir = base_path.replace("/pub/allure-report-", "/dev/tests/acceptance/_output/allure-results-")

    if not os.path.exists(source_dir):
        return {{"source_removed": 0, "source_files": [], "source_error": f"Source dir not found: {{source_dir}}"}}

    source_removed = []

    try:
        files = os.listdir(source_dir)
    except Exception as e:
        return {{"source_removed": 0, "source_files": [], "source_error": str(e)}}

    for f in files:
        if not f.endswith("-testsuite.xml"):
            continue

        filepath = f"{{source_dir}}/{{f}}"
        try:
            tree = ET.parse(filepath)
            root = tree.getroot()

            # Get suite name from <test-suite><name> or root <name>
            suite_el = root.find("name")
            suite = suite_el.text if suite_el is not None else ""

            # Apply suite filter (same logic as report cleanup)
            if in_suites:
                # At least one in_suite must match
                if not any(s in suite for s in in_suites):
                    continue
            if not_in_suites:
                # None of not_in_suites should match
                if any(s in suite for s in not_in_suites):
                    continue

            # Find test name in <test-case><name>TESTID</name>
            should_delete = False
            matched_name = ""
            for test_case in root.findall(".//test-case"):
                name_el = test_case.find("name")
                if name_el is None:
                    continue
                name = name_el.text or ""

                # Check if test ID matches
                if matches_test_id(name, test_ids, strict):
                    should_delete = True
                    matched_name = name
                    break

            if should_delete:
                source_removed.append({{"file": f, "name": matched_name, "suite": suite}})
                if not dry_run:
                    os.remove(filepath)

        except Exception as e:
            continue

    return {{
        "source_removed": len(source_removed),
        "source_files": source_removed[:5],
        "source_more": len(source_removed) - 5 if len(source_removed) > 5 else 0
    }}

def cleanup_environment(base_path, test_ids, in_suites, not_in_suites, strict=False, dry_run=False):
    """Clean up test entries from a single environment."""
    tc_dir = f"{{base_path}}/data/test-cases"

    if not os.path.exists(tc_dir):
        return {{"error": "Directory not found", "path": tc_dir}}

    removed = []
    removed_uids = []

    # Find and remove matching test-case files
    for f in os.listdir(tc_dir):
        if not f.endswith(".json"):
            continue

        filepath = f"{{tc_dir}}/{{f}}"
        try:
            with open(filepath) as fh:
                data = json.load(fh)
        except Exception as e:
            continue

        name = data.get("name", "")
        labels = data.get("labels", [])
        suite = next((l.get("value", "") for l in labels if l.get("name") == "suite"), "")

        # Check if test ID matches
        if not matches_test_id(name, test_ids, strict):
            continue

        # Apply suite filter
        if not ({suite_filter}):
            continue

        # This entry should be removed
        uid = data.get("uid")
        history_id = data.get("historyId")

        removed.append({{
            "uid": uid,
            "name": name,
            "suite": suite,
            "historyId": history_id
        }})
        removed_uids.append(uid)

        if not dry_run:
            os.remove(filepath)

    if not removed:
        return {{"removed": 0, "entries": []}}

    # Helper to remove by UID from nested structure
    def remove_by_uid(node, uids):
        if isinstance(node, dict):
            if "children" in node:
                node["children"] = [
                    c for c in node["children"]
                    if not (isinstance(c, dict) and c.get("uid") in uids)
                ]
                for c in node["children"]:
                    remove_by_uid(c, uids)
        elif isinstance(node, list):
            for item in node:
                remove_by_uid(item, uids)

    # Update behaviors.json and suites.json
    for jf in ["behaviors.json", "suites.json"]:
        jpath = f"{{base_path}}/data/{{jf}}"
        if not os.path.exists(jpath):
            continue

        try:
            with open(jpath) as fh:
                jdata = json.load(fh)

            if not dry_run:
                # Backup
                with open(f"{{jpath}}.bak", "w") as fh:
                    json.dump(jdata, fh)

                remove_by_uid(jdata, set(removed_uids))

                with open(jpath, "w") as fh:
                    json.dump(jdata, fh, separators=(",", ":"))
        except Exception as e:
            pass

    # Update history.json
    history_ids = set(r["historyId"] for r in removed if r.get("historyId"))
    hist_path = f"{{base_path}}/history/history.json"

    if history_ids and os.path.exists(hist_path):
        try:
            with open(hist_path) as fh:
                hist = json.load(fh)

            if not dry_run:
                with open(f"{{hist_path}}.bak", "w") as fh:
                    json.dump(hist, fh)

                for hid in history_ids:
                    if hid in hist:
                        del hist[hid]

                with open(hist_path, "w") as fh:
                    json.dump(hist, fh, separators=(",", ":"))
        except Exception as e:
            pass

    # Also clean source results (XML testsuite files)
    source_result = cleanup_source_results(base_path, test_ids, in_suites, not_in_suites, strict, dry_run)

    return {{
        "removed": len(removed),
        "entries": removed[:5],
        "more": len(removed) - 5 if len(removed) > 5 else 0,
        **source_result
    }}


# Main execution
test_ids = [{test_ids_str}]
in_suites = [{in_suites_str}]
not_in_suites = [{not_in_suites_str}]
dry_run = {str(dry_run)}
strict = {str(strict)}
environments = {{}}  # Will be populated by caller

base_path_template = os.environ.get("BASE_PATH", "{DEFAULT_BASE_PATH}")
envs_str = os.environ.get("ENVIRONMENTS", "")

if not envs_str:
    print("ERROR: ENVIRONMENTS not set", file=sys.stderr)
    sys.exit(1)

results = {{}}
for env in envs_str.split(","):
    env = env.strip()
    if not env:
        continue
    base_path = base_path_template.replace("{{env}}", env)
    results[env] = cleanup_environment(base_path, test_ids, in_suites, not_in_suites, strict, dry_run)

print(json.dumps(results, indent=2))
'''
    return script


def run_remote_cleanup(
    ssh_host: str,
    environments: list[str],
    test_ids: list[str],
    base_path: str,
    in_suites: Optional[list[str]] = None,
    not_in_suites: Optional[list[str]] = None,
    dry_run: bool = False,
    strict: bool = False,
    use_sudo: bool = False
) -> dict:
    """Run cleanup script on remote host."""

    script = generate_cleanup_script(test_ids, in_suites, not_in_suites, dry_run, strict)

    # Create temp file with script
    with tempfile.NamedTemporaryFile(mode='w', suffix='.py', delete=False) as f:
        f.write(script)
        temp_path = f.name

    try:
        # Copy script to remote
        remote_path = '/tmp/allure_cleanup_batch.py'
        subprocess.run(
            ['scp', temp_path, f'{ssh_host}:{remote_path}'],
            check=True, capture_output=True
        )

        # Run script on remote
        env_vars = f'ENVIRONMENTS="{",".join(environments)}" BASE_PATH="{base_path}"'
        python_cmd = f'{env_vars} python3 {remote_path}'

        if use_sudo:
            python_cmd = f'sudo {python_cmd}'

        result = subprocess.run(
            ['ssh', ssh_host, python_cmd],
            capture_output=True, text=True
        )

        if result.returncode != 0:
            print(f"Error: {result.stderr}", file=sys.stderr)
            return {}

        return json.loads(result.stdout)

    finally:
        os.unlink(temp_path)


def run_local_cleanup(
    environments: list[str],
    test_ids: list[str],
    base_path: str,
    in_suites: Optional[list[str]] = None,
    not_in_suites: Optional[list[str]] = None,
    dry_run: bool = False,
    strict: bool = False
) -> dict:
    """Run cleanup locally."""

    script = generate_cleanup_script(test_ids, in_suites, not_in_suites, dry_run, strict)

    # Set environment variables and run
    env = os.environ.copy()
    env['ENVIRONMENTS'] = ','.join(environments)
    env['BASE_PATH'] = base_path

    result = subprocess.run(
        [sys.executable, '-c', script],
        capture_output=True, text=True, env=env
    )

    if result.returncode != 0:
        print(f"Error: {result.stderr}", file=sys.stderr)
        return {}

    return json.loads(result.stdout)


def print_results(results: dict, dry_run: bool = False):
    """Print cleanup results in a formatted way."""
    prefix = "[DRY RUN] " if dry_run else ""

    total_removed = 0
    total_source_removed = 0

    for env, result in results.items():
        if 'error' in result:
            print(f"\n{env}: {result['error']} ({result.get('path', '')})")
            continue

        removed = result.get('removed', 0)
        source_removed = result.get('source_removed', 0)
        total_removed += removed
        total_source_removed += source_removed

        print(f"\n{env}: {prefix}Removed {removed} report entries, {source_removed} source files")

        for entry in result.get('entries', []):
            print(f"  - {entry['name']}")
            print(f"    Suite: {entry['suite']}")

        if result.get('more', 0) > 0:
            print(f"  ... and {result['more']} more report entries")

        # Show source file errors if any
        if result.get('source_error'):
            print(f"  ⚠️ Source cleanup error: {result['source_error']}")

        # Show source files removed
        for sf in result.get('source_files', []):
            suite_info = sf.get('suite', '')
            suite_short = suite_info.split('\\')[-1] if suite_info else 'unknown'
            print(f"  📁 {sf['file']} ({sf['name']}) [{suite_short}]")

        if result.get('source_more', 0) > 0:
            print(f"  ... and {result['source_more']} more source files")

    print(f"\n{'=' * 50}")
    print(f"Total: {prefix}{total_removed} report entries + {total_source_removed} source files removed across {len(results)} environment(s)")


def main():
    parser = argparse.ArgumentParser(
        description='Remove test execution history from Allure reports',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__
    )

    parser.add_argument(
        '--test-id', '-t',
        action='append',
        required=True,
        help='Test ID pattern to remove (can be used multiple times)'
    )

    parser.add_argument(
        '--env', '-e',
        default='all',
        help='Environments: all, us, es, stage, preprod, or comma-separated list (default: all)'
    )

    parser.add_argument(
        '--in-suite',
        action='append',
        help='Only remove from these suites (can be used multiple times)'
    )

    parser.add_argument(
        '--not-in-suite',
        action='append',
        help='Do not remove from these suites (can be used multiple times)'
    )

    parser.add_argument(
        '--ssh', '-s',
        default=DEFAULT_SSH_HOST,
        help=f'SSH host alias (default: {DEFAULT_SSH_HOST}, use "local" for local execution)'
    )

    parser.add_argument(
        '--base-path', '-p',
        default=DEFAULT_BASE_PATH,
        help=f'Base path template with {{env}} placeholder (default: {DEFAULT_BASE_PATH})'
    )

    parser.add_argument(
        '--dry-run', '-n',
        action='store_true',
        help='Show what would be removed without making changes'
    )

    parser.add_argument(
        '--sudo',
        action='store_true',
        help='Use sudo for file operations'
    )

    parser.add_argument(
        '--strict',
        action='store_true',
        help='Use strict (word boundary) matching for test IDs instead of substring matching'
    )

    args = parser.parse_args()

    # Expand environments
    environments = expand_environments(args.env)

    # Print header
    print("Allure History Cleanup")
    print("=" * 50)
    print(f"Test IDs: {', '.join(args.test_id)}")
    print(f"Environments: {', '.join(environments)}")
    print(f"Base path: {args.base_path}")

    if args.in_suite:
        print(f"In suites: {', '.join(args.in_suite)}")
    if args.not_in_suite:
        print(f"Not in suites: {', '.join(args.not_in_suite)}")

    print(f"SSH host: {args.ssh}")
    print(f"Dry run: {args.dry_run}")
    print(f"Strict matching: {args.strict}")
    print(f"Use sudo: {args.sudo}")

    # Run cleanup
    if args.ssh == 'local':
        results = run_local_cleanup(
            environments=environments,
            test_ids=args.test_id,
            base_path=args.base_path,
            in_suites=args.in_suite,
            not_in_suites=args.not_in_suite,
            dry_run=args.dry_run,
            strict=args.strict
        )
    else:
        results = run_remote_cleanup(
            ssh_host=args.ssh,
            environments=environments,
            test_ids=args.test_id,
            base_path=args.base_path,
            in_suites=args.in_suite,
            not_in_suites=args.not_in_suite,
            dry_run=args.dry_run,
            strict=args.strict,
            use_sudo=args.sudo
        )

    # Print results
    print_results(results, args.dry_run)

    return 0


if __name__ == '__main__':
    sys.exit(main())
