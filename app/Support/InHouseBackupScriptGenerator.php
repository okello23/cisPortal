<?php

namespace App\Support;

use App\Models\InHouseBackupConfiguration;

class InHouseBackupScriptGenerator
{
    public function render(InHouseBackupConfiguration $configuration): string
    {
        $dumpCommand = match ($configuration->database_type) {
            'mongodb' => 'mongodump --username "$DB_USER" --password "$DB_PASS" --authenticationDatabase admin --db "$DB_NAME" --archive | gzip > "$BACKUP_FILE"',
            'postgresql' => 'PGPASSWORD="$DB_PASS" pg_dump -U "$DB_USER" -d "$DB_NAME" | gzip > "$BACKUP_FILE"',
            default => 'mysqldump --single-transaction --skip-lock-tables --no-tablespaces --quick --routines --triggers -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_FILE"',
        };

        $engineLabel = match ($configuration->database_type) {
            'mongodb' => 'MongoDB',
            'postgresql' => 'PostgreSQL',
            default => 'MySQL',
        };

        $backupServer = (string) (config('alis_backup.facility_offsite_server_ip') ?: config('alis_backup.server'));
        $backupPort = (string) config('alis_backup.port', 22);
        $backupUser = (string) config('alis_backup.user', 'backupuser');
        $remoteDirectory = $configuration->backup_directory_path;

        $dbName = $this->escape($configuration->database_name);
        $dbUser = $this->escape($configuration->database_username);
        $dbPass = $this->escape($configuration->database_password);
        $remoteServer = $this->escape($backupServer);
        $remotePort = $this->escape($backupPort);
        $remoteUser = $this->escape($backupUser);
        $remoteDir = $this->escape($remoteDirectory);

        return <<<BASH
#!/bin/bash
set -euo pipefail

# IN-HOUSE SYSTEM OFF-SITE DATABASE BACKUP
# Database engine: {$configuration->database_type}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}
BACKUP_ROOT="\$HOME/.backup"
BACKUP_DIR="\$BACKUP_ROOT"
BACKUP_FILE="\$BACKUP_DIR/\${DB_NAME}_\$(date +"%F_%H-%M-%S").sql.gz"

REMOTE_SERVER={$remoteServer}
REMOTE_PORT={$remotePort}
REMOTE_USER={$remoteUser}
REMOTE_DIRECTORY={$remoteDir}

SSH_DIR="\$HOME/.ssh"
SSH_KEY="\$SSH_DIR/alis_backup_ed25519"
KNOWN_HOSTS_FILE="\$SSH_DIR/known_hosts"
LOG_FILE="\$BACKUP_ROOT/backup.log"

# Logging function that prints to console and appends to log file
log() {
    echo "\$(date +"%F %T") \$1" | tee -a "\$LOG_FILE"
}

# Create backup root directory
mkdir -p "\$BACKUP_DIR"

log "Starting Database Backup for \$DB_NAME"
log "Creating compressed {$engineLabel} database dump..."

# Perform optimized, compressed dump
{$dumpCommand}

# Database integrity check
if [ ! -s "\$BACKUP_FILE" ]; then
    log "ERROR: Database backup file is empty"
    rm -f "\$BACKUP_FILE"
    exit 1
fi
log "Database backup created successfully: \$BACKUP_FILE"

# Prepare SSH directories and keys
mkdir -p "\$SSH_DIR"
chmod 700 "\$SSH_DIR"
touch "\$KNOWN_HOSTS_FILE"
chmod 600 "\$KNOWN_HOSTS_FILE"

# Register remote host key dynamically if not already registered
if ! ssh-keygen -F "\$REMOTE_SERVER" -f "\$KNOWN_HOSTS_FILE" >/dev/null 2>&1; then
    log "Registering backup server host key..."
    if ! ssh-keyscan -p "\$REMOTE_PORT" -H "\$REMOTE_SERVER" >> "\$KNOWN_HOSTS_FILE" 2>> "\$LOG_FILE"; then
        log "ERROR: Failed to retrieve the backup server host key"
        exit 1
    fi
    chmod 600 "\$KNOWN_HOSTS_FILE"
    log "Backup server host key registered successfully."
fi

log "Starting upload to central backup server..."

# Temporarily disable 'exit on error' to capture SFTP status codes safely
set +e
sftp -i "\$SSH_KEY" -P "\$REMOTE_PORT" -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile="\$KNOWN_HOSTS_FILE" "\$REMOTE_USER@\$REMOTE_SERVER" <<EOF >> "\$LOG_FILE" 2>&1
cd "\$REMOTE_DIRECTORY"
put "\$BACKUP_FILE"
bye
EOF
SFTP_STATUS=\$?
set -e

# Verify upload exit status
if [ \$SFTP_STATUS -eq 0 ]; then
    log "Backup successfully uploaded to central backup server."
else
    log "ERROR: Backup upload failed with SFTP exit status: \$SFTP_STATUS"
    exit \$SFTP_STATUS
fi

log "Starting local backup cleanup..."

# Retention logic with protective 24h dump check and dynamic file counting
if find "\$BACKUP_DIR" -type f -name '*.sql.gz' -mmin -1440 -print -quit | grep -q .; then
    # Count how many files will be deleted
    DELETED_COUNT=\$(find "\$BACKUP_DIR" -type f -name '*.sql.gz' -mtime +3 | wc -l)

    # Perform deletion safely
    find "\$BACKUP_DIR" -type f -name '*.sql.gz' -mtime +3 -delete

    log "Local backup cleanup completed. Deleted \$DELETED_COUNT backup file(s) older than 3 days."
else
    log "Skipping retention cleanup: no new dump found in the last 24 hours to safeguard existing backups."
fi

log "In-house database backup completed successfully."

BASH;
    }

    private function escape(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
