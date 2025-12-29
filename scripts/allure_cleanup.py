#!/usr/bin/env python3
"""
Allure Test History Cleanup Script

Removes test execution history from Allure reports for specified test IDs.
Can be run locally or via SSH on a remote server.

Usage:
    # Local execution:
    python allure_cleanup.py --path /path/to/allure-report --test-id "MOEC-2609US"

    # Remote execution via SSH:
    python allure_cleanup.py --ssh abb --path /home/ubuntu/ABBTests/src/pub/allure-report-stage-us --test-id "MOEC-2609US"

    # Dry run (show what would be removed):
    python allure_cleanup.py --ssh abb --path /path/to/report --test-id "MOEC-2609US" --dry-run

    # Multiple tests:
    python allure_cleanup.py --ssh abb --path /path/to/report --test-id "MOEC-2609US" --test-id "MOEC-1234"
"""

import argparse
import json
import subprocess
import sys
import hashlib
from pathlib import Path


def run_command(cmd: str, ssh_host: str = None, use_sudo: bool = False) -> tuple[int, str, str]:
    """Run a command locally or via SSH."""
    if use_sudo:
        cmd = f'sudo {cmd}'
    if ssh_host:
        cmd = f'ssh {ssh_host} "{cmd}"'

    result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    return result.returncode, result.stdout, result.stderr


def read_remote_file(path: str, ssh_host: str = None) -> str:
    """Read file content from local or remote."""
    cmd = f"cat {path}"
    code, stdout, stderr = run_command(cmd, ssh_host)
    if code != 0:
        raise FileNotFoundError(f"Cannot read {path}: {stderr}")
    return stdout


def write_remote_file(path: str, content: str, ssh_host: str = None, use_sudo: bool = False) -> None:
    """Write content to local or remote file."""
    if ssh_host:
        # Use tee with sudo for permission issues
        if use_sudo:
            cmd = f"ssh {ssh_host} 'sudo tee {path} > /dev/null'"
        else:
            cmd = f"ssh {ssh_host} 'cat > {path}'"
        proc = subprocess.Popen(cmd, shell=True, stdin=subprocess.PIPE, text=True)
        proc.communicate(input=content)
        if proc.returncode != 0:
            raise IOError(f"Failed to write to {path}")
    else:
        Path(path).write_text(content)


def backup_file(path: str, ssh_host: str = None, use_sudo: bool = False) -> str:
    """Create a backup of the file."""
    backup_path = f"{path}.bak"
    cmd = f"cp {path} {backup_path}"
    code, _, stderr = run_command(cmd, ssh_host, use_sudo)
    if code != 0:
        raise IOError(f"Failed to backup {path}: {stderr}")
    return backup_path


def find_test_hashes(report_path: str, test_ids: list[str], ssh_host: str = None) -> dict:
    """Find test case hashes by searching in behaviors.json and categories.json."""
    found_tests = {}

    # Search in behaviors.json
    try:
        behaviors_path = f"{report_path}/data/behaviors.json"
        content = read_remote_file(behaviors_path, ssh_host)
        behaviors = json.loads(content)

        def search_children(node, test_ids, results):
            if isinstance(node, dict):
                if 'name' in node and 'uid' in node:
                    for test_id in test_ids:
                        if test_id.lower() in node.get('name', '').lower():
                            results[node['name']] = {
                                'uid': node['uid'],
                                'status': node.get('status'),
                                'retriesCount': node.get('retriesCount', 0)
                            }
                if 'children' in node:
                    for child in node['children']:
                        search_children(child, test_ids, results)
            elif isinstance(node, list):
                for item in node:
                    search_children(item, test_ids, results)

        search_children(behaviors, test_ids, found_tests)
    except Exception as e:
        print(f"Warning: Could not search behaviors.json: {e}")

    return found_tests


def find_history_keys(report_path: str, test_names: list[str], ssh_host: str = None) -> list[str]:
    """Find history.json keys that match the test names."""
    keys_to_remove = []

    # Read suites.csv to map test names to history IDs
    try:
        suites_path = f"{report_path}/data/suites.csv"
        content = read_remote_file(suites_path, ssh_host)

        for line in content.split('\n'):
            for test_name in test_names:
                if test_name.lower() in line.lower():
                    # The historyId is typically calculated from test full name
                    # We'll need to search history.json directly
                    pass
    except Exception as e:
        print(f"Warning: Could not read suites.csv: {e}")

    # Read history.json and search for matching entries
    try:
        history_path = f"{report_path}/history/history.json"
        content = read_remote_file(history_path, ssh_host)
        history = json.loads(content)

        # Search through categories.json for test name to historyId mapping
        categories_path = f"{report_path}/data/categories.json"
        cat_content = read_remote_file(categories_path, ssh_host)

        for test_name in test_names:
            if test_name.lower() in cat_content.lower():
                # Find the historyId by searching for test name patterns
                # The history ID is a hash - we need to find it in test-cases folder
                pass

    except Exception as e:
        print(f"Warning: Could not process history: {e}")

    return keys_to_remove


def get_test_history_ids(report_path: str, test_pattern: str, ssh_host: str = None) -> list[str]:
    """Get historyId values for tests matching pattern by searching test-cases."""
    history_ids = []

    # Search in test-cases JSON files for matching test names
    cmd = f"grep -r '{test_pattern}' {report_path}/data/test-cases/ 2>/dev/null | head -20"
    code, stdout, stderr = run_command(cmd, ssh_host)

    if stdout:
        # Parse the grep output to find historyId
        for line in stdout.split('\n'):
            if 'historyId' in line:
                try:
                    # Extract historyId from JSON content
                    import re
                    match = re.search(r'"historyId"\s*:\s*"([^"]+)"', line)
                    if match:
                        history_ids.append(match.group(1))
                except:
                    pass

    # Alternative: search directly in test-cases files
    cmd = f"find {report_path}/data/test-cases -name '*.json' -exec grep -l '{test_pattern}' {{}} \\; 2>/dev/null"
    code, stdout, stderr = run_command(cmd, ssh_host)

    if stdout:
        for file_path in stdout.strip().split('\n'):
            if file_path:
                try:
                    content = read_remote_file(file_path, ssh_host)
                    data = json.loads(content)
                    if 'historyId' in data:
                        history_ids.append(data['historyId'])
                except Exception as e:
                    print(f"Warning: Could not parse {file_path}: {e}")

    return list(set(history_ids))


def remove_from_history(report_path: str, history_ids: list[str], ssh_host: str = None, dry_run: bool = False, use_sudo: bool = False) -> int:
    """Remove entries from history.json by historyId."""
    history_path = f"{report_path}/history/history.json"

    try:
        content = read_remote_file(history_path, ssh_host)
        history = json.loads(content)
    except Exception as e:
        print(f"Error reading history.json: {e}")
        return 0

    original_count = len(history)
    removed_count = 0

    for history_id in history_ids:
        if history_id in history:
            if dry_run:
                print(f"  [DRY RUN] Would remove history entry: {history_id}")
            else:
                del history[history_id]
            removed_count += 1

    if removed_count > 0 and not dry_run:
        # Backup original
        backup_file(history_path, ssh_host, use_sudo)

        # Write updated history
        new_content = json.dumps(history, separators=(',', ':'))
        write_remote_file(history_path, new_content, ssh_host, use_sudo)
        print(f"  Removed {removed_count} entries from history.json (backup created)")

    return removed_count


def remove_test_case_files(report_path: str, test_uids: list[str], ssh_host: str = None, dry_run: bool = False, use_sudo: bool = False) -> int:
    """Remove test-case JSON files by UID."""
    removed = 0
    for uid in test_uids:
        file_path = f"{report_path}/data/test-cases/{uid}.json"
        if dry_run:
            print(f"  [DRY RUN] Would remove: {file_path}")
            removed += 1
        else:
            cmd = f"rm -f {file_path}"
            code, _, stderr = run_command(cmd, ssh_host, use_sudo)
            if code == 0:
                print(f"  Removed: {file_path}")
                removed += 1
            else:
                print(f"  Warning: Could not remove {file_path}: {stderr}")
    return removed


def remove_from_json_file(report_path: str, filename: str, test_uids: list[str], ssh_host: str = None, dry_run: bool = False, use_sudo: bool = False) -> int:
    """Remove test entries from a JSON file (behaviors.json, suites.json) by UID."""
    file_path = f"{report_path}/data/{filename}"

    try:
        content = read_remote_file(file_path, ssh_host)
        data = json.loads(content)
    except Exception as e:
        print(f"  Warning: Could not read {filename}: {e}")
        return 0

    removed = 0

    def remove_by_uid(node, uids_to_remove):
        """Recursively remove nodes with matching UIDs."""
        nonlocal removed
        if isinstance(node, dict):
            if 'children' in node and isinstance(node['children'], list):
                original_len = len(node['children'])
                node['children'] = [
                    child for child in node['children']
                    if not (isinstance(child, dict) and child.get('uid') in uids_to_remove)
                ]
                removed += original_len - len(node['children'])
                for child in node['children']:
                    remove_by_uid(child, uids_to_remove)
        elif isinstance(node, list):
            for item in node:
                remove_by_uid(item, uids_to_remove)

    if dry_run:
        # Count matches without modifying
        def count_matches(node, uids):
            count = 0
            if isinstance(node, dict):
                if node.get('uid') in uids:
                    count += 1
                if 'children' in node:
                    for child in node['children']:
                        count += count_matches(child, uids)
            elif isinstance(node, list):
                for item in node:
                    count += count_matches(item, uids)
            return count

        matches = count_matches(data, set(test_uids))
        if matches > 0:
            print(f"  [DRY RUN] Would remove {matches} entries from {filename}")
        return matches

    remove_by_uid(data, set(test_uids))

    if removed > 0:
        backup_file(file_path, ssh_host, use_sudo)
        new_content = json.dumps(data, separators=(',', ':'))
        write_remote_file(file_path, new_content, ssh_host, use_sudo)
        print(f"  Removed {removed} entries from {filename} (backup created)")

    return removed


def main():
    parser = argparse.ArgumentParser(
        description='Remove test execution history from Allure reports',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__
    )
    parser.add_argument('--ssh', '-s', help='SSH host alias (from ~/.ssh/config)')
    parser.add_argument('--path', '-p', required=True, help='Path to allure report directory')
    parser.add_argument('--test-id', '-t', action='append', required=True,
                        help='Test ID pattern to remove (can be used multiple times)')
    parser.add_argument('--dry-run', '-n', action='store_true',
                        help='Show what would be removed without making changes')
    parser.add_argument('--sudo', action='store_true',
                        help='Use sudo for file operations (needed for permission issues)')

    args = parser.parse_args()

    print(f"Allure History Cleanup")
    print(f"=" * 50)
    print(f"Report path: {args.path}")
    print(f"SSH host: {args.ssh or 'local'}")
    print(f"Test IDs: {', '.join(args.test_id)}")
    print(f"Dry run: {args.dry_run}")
    print(f"Use sudo: {args.sudo}")
    print()

    # Step 1: Find tests in report
    print("Step 1: Searching for tests in report...")
    found_tests = find_test_hashes(args.path, args.test_id, args.ssh)

    if found_tests:
        print(f"  Found {len(found_tests)} matching test(s):")
        for name, info in found_tests.items():
            print(f"    - {name}")
            print(f"      UID: {info['uid']}, Status: {info['status']}, Retries: {info['retriesCount']}")
    else:
        print("  No matching tests found in behaviors.json")

    # Step 2: Find history IDs
    print("\nStep 2: Finding history IDs...")
    history_ids = []
    for test_id in args.test_id:
        ids = get_test_history_ids(args.path, test_id, args.ssh)
        history_ids.extend(ids)

    history_ids = list(set(history_ids))

    if history_ids:
        print(f"  Found {len(history_ids)} history ID(s):")
        for hid in history_ids:
            print(f"    - {hid}")
    else:
        print("  No history IDs found")
        return 1

    # Collect UIDs from found tests
    test_uids = [info['uid'] for info in found_tests.values()]

    # Step 3: Remove test-case files
    print("\nStep 3: Removing test-case files...")
    if test_uids:
        remove_test_case_files(args.path, test_uids, args.ssh, args.dry_run, args.sudo)
    else:
        print("  No UIDs found to remove")

    # Step 4: Remove from behaviors.json
    print("\nStep 4: Removing from behaviors.json...")
    if test_uids:
        remove_from_json_file(args.path, "behaviors.json", test_uids, args.ssh, args.dry_run, args.sudo)

    # Step 5: Remove from suites.json
    print("\nStep 5: Removing from suites.json...")
    if test_uids:
        remove_from_json_file(args.path, "suites.json", test_uids, args.ssh, args.dry_run, args.sudo)

    # Step 6: Remove from history.json
    print("\nStep 6: Removing from history.json...")
    removed = remove_from_history(args.path, history_ids, args.ssh, args.dry_run, args.sudo)

    if args.dry_run:
        print(f"\n[DRY RUN] Complete cleanup preview finished")
    else:
        print(f"\nDone! Complete cleanup finished")

    return 0


if __name__ == '__main__':
    sys.exit(main())
