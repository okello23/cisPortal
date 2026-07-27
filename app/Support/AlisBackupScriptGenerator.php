<?php

namespace App\Support;

use App\Models\AlisBackupConfiguration;

class AlisBackupScriptGenerator
{
    public function generate(AlisBackupConfiguration $configuration): string
    {
        return <<<'BASH'
#!/bin/bash

set -euo pipefail

# ============================================================
# A-LIS OFF-SITE DATABASE BACKUP
# ============================================================

DB_NAME={{DB_NAME}}
DB_USER={{DB_USER}}
DB_PASS={{DB_PASS}}

FACILITY={{FACILITY}}

BACKUP_ROOT="$HOME/.backup"
BACKUP_DIR="$BACKUP_ROOT"

REMOTE_SERVER={{REMOTE_SERVER}}
REMOTE_USER={{REMOTE_USER}}
REMOTE_PORT={{REMOTE_PORT}}
REMOTE_ROOT={{REMOTE_ROOT}}
REMOTE_FACILITY_DIR="$REMOTE_ROOT/$FACILITY"

SSH_KEY="$HOME/.ssh/alis_backup_ed25519"
SSH_DIR="$HOME/.ssh"
KNOWN_HOSTS_FILE="$SSH_DIR/known_hosts"

LOG_FILE="$BACKUP_ROOT/backup.log"

DATE=$(date +"%F_%H-%M-%S")

BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz"

log() {
    echo "[$(date '+%F %T')] $1" | tee -a "$LOG_FILE"
}

mkdir -p "$BACKUP_DIR"

log "================================================"
log "Starting A-LIS database backup"
log "Facility: $FACILITY"
log "================================================"

log "Creating compressed MySQL database dump..."

mysqldump \
    --single-transaction \
    --skip-lock-tables \
    --no-tablespaces \
    --quick \
    --routines \
    --triggers \
    -u "$DB_USER" \
    -p"$DB_PASS" \
    "$DB_NAME" \
    | gzip > "$BACKUP_FILE"

if [ ! -s "$BACKUP_FILE" ]; then
    log "ERROR: Database backup file is empty."
    rm -f "$BACKUP_FILE"
    exit 1
fi

log "Database backup created successfully."
log "Backup file: $BACKUP_FILE"

mkdir -p "$SSH_DIR"
chmod 700 "$SSH_DIR"

touch "$KNOWN_HOSTS_FILE"
chmod 600 "$KNOWN_HOSTS_FILE"

if ! ssh-keygen \
    -F "$REMOTE_SERVER" \
    -f "$KNOWN_HOSTS_FILE" \
    >/dev/null 2>&1; then

    log "Registering backup server host key..."

    if ! ssh-keyscan \
        -p 22 \
        -H "$REMOTE_SERVER" \
        >> "$KNOWN_HOSTS_FILE" 2>> "$LOG_FILE"; then

        log "ERROR: Failed to retrieve the backup server host key."
        exit 1
    fi

    chmod 600 "$KNOWN_HOSTS_FILE"
    log "Backup server host key registered successfully."
fi

log "Starting upload to central backup server... $REMOTE_FACILITY_DIR/"

set +e

sftp \
    -i "$SSH_KEY" \
    -P 22 \
    -o BatchMode=yes \
    -o StrictHostKeyChecking=yes \
    -o UserKnownHostsFile="$KNOWN_HOSTS_FILE" \
    "$REMOTE_USER@$REMOTE_SERVER" <<EOF >> "$LOG_FILE" 2>&1
cd "$REMOTE_FACILITY_DIR"
put "$BACKUP_FILE"
bye
EOF

SFTP_STATUS=$?

set -e

if [ "$SFTP_STATUS" -eq 0 ]; then
    log "Backup successfully uploaded to central backup server."
else
    log "ERROR: Backup upload failed."
    log "SFTP exit status: $SFTP_STATUS"
    exit "$SFTP_STATUS"
fi

log "Starting local backup cleanup..."
log "Deleting local database backups older than 3 days..."

DELETED_COUNT=$(find "$BACKUP_ROOT" \
    -type f \
    -mtime +3 \
    -name "*.sql.gz" \
    -print \
    -delete \
    | wc -l)

log "Local backup cleanup completed."
log "Deleted $DELETED_COUNT backup file(s) older than 3 days."

log "A-LIS database backup completed successfully."
log "================================================"
BASH;
    }

    public function render(AlisBackupConfiguration $configuration): string
    {
        return strtr($this->generate($configuration), [
            '{{DB_NAME}}' => $this->shellEscape($configuration->database_name),
            '{{DB_USER}}' => $this->shellEscape($configuration->database_username),
            '{{DB_PASS}}' => $this->shellEscape($configuration->database_password),
            '{{FACILITY}}' => $this->shellEscape($configuration->backup_directory_name),
            '{{REMOTE_SERVER}}' => $this->shellEscape((string) config('alis_backup.facility_offsite_server_ip')),
            '{{REMOTE_USER}}' => $this->shellEscape('backupuser'),
            '{{REMOTE_PORT}}' => $this->shellEscape('22'),
            '{{REMOTE_ROOT}}' => $this->shellEscape((string) config('alis_backup.root')),
        ]);
    }

    private function shellEscape(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
