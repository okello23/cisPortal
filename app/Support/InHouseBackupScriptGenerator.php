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

        $backupServer = (string) (config('alis_backup.facility_offsite_server_ip') ?: config('alis_backup.server'));

        $remoteDirectory = rtrim((string) config('alis_backup.dumps_root', '/dumps'), '/').'/inhouse_systems/'.$configuration->backup_directory_name;
        return "#!/bin/bash\nset -euo pipefail\n\n# IN-HOUSE SYSTEM OFF-SITE DATABASE BACKUP\n# Database engine: {$configuration->database_type}\nDB_NAME=".$this->escape($configuration->database_name)."\nDB_USER=".$this->escape($configuration->database_username)."\nDB_PASS=".$this->escape($configuration->database_password)."\nBACKUP_ROOT=\"\$HOME/.backup\"\nBACKUP_FILE=\"\$BACKUP_ROOT/\${DB_NAME}_\$(date +\"%F_%H-%M-%S\").sql.gz\"\nREMOTE_SERVER=".$this->escape($backupServer)."\nREMOTE_USER=".$this->escape((string) config('alis_backup.user', 'backupuser'))."\nREMOTE_DIRECTORY=".$this->escape($remoteDirectory)."\nSSH_KEY=\"\$HOME/.ssh/in_house_backup_ed25519\"\n\nmkdir -p \"\$BACKUP_ROOT\"\n{$dumpCommand}\n[ -s \"\$BACKUP_FILE\" ] || { echo 'Backup file is empty.'; exit 1; }\nsftp -i \"\$SSH_KEY\" -P 22 -o BatchMode=yes \"\$REMOTE_USER@\$REMOTE_SERVER\" <<EOF\ncd \"\$REMOTE_DIRECTORY\"\nput \"\$BACKUP_FILE\"\nbye\nEOF\nif find \"\$BACKUP_ROOT\" -type f -name '*.sql.gz' -mmin -1440 -print -quit | grep -q .; then\n  find \"\$BACKUP_ROOT\" -type f -name '*.sql.gz' -mtime +3 -delete\nelse\n  echo 'Skipping retention cleanup: no new dump in the last 24 hours.'\nfi\necho 'In-house backup completed successfully.'\n";
    }

    private function escape(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
