# SSH Keys for Git Clone

This directory is mounted to `/home/www-data/.ssh` in containers for git clone operations.

## Setup

1. Copy your SSH private key:
   ```bash
   cp ~/.ssh/id_ed25519 docker/ssh/
   ```

2. Create SSH config:
   ```bash
   cat > docker/ssh/config << 'EOF'
   Host *
       StrictHostKeyChecking no
       UserKnownHostsFile /dev/null
       IdentityFile ~/.ssh/id_ed25519
   EOF
   ```

3. Set permissions (for Alpine www-data, UID 82):
   ```bash
   sudo chown 82:82 docker/ssh/id_ed25519 docker/ssh/config
   chmod 600 docker/ssh/id_ed25519
   chmod 644 docker/ssh/config
   ```

## Security

- **Do NOT commit SSH keys to git** - they are in `.gitignore`
- Keys are mounted read-only (`:ro`)
- `GIT_SSH_COMMAND` env var bypasses host key verification for automation
