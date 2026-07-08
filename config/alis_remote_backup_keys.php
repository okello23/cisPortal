<?php

return [
    'allow_rsa' => (bool) env('ALIS_BACKUP_ALLOW_RSA', false),
    'host' => env('BACKUP_SERVER_HOST', '10.200.0.160'),
    'user' => env('BACKUP_SERVER_USER', 'cis-backup-admin'),
    'authorized_keys_path' => env('BACKUP_AUTHORIZED_KEYS_PATH', '/home/backupuser/.ssh/authorized_keys'),
    'authorized_keys_owner' => env('BACKUP_AUTHORIZED_KEYS_OWNER', 'backupuser'),
    'authorized_keys_group' => env('BACKUP_AUTHORIZED_KEYS_GROUP', 'backupuser'),
    'authorized_keys_mode' => env('BACKUP_AUTHORIZED_KEYS_MODE', '600'),
    'remote_temp_path' => env('BACKUP_REMOTE_TEMP_PATH', '/tmp/authorized_keys.generated'),
    'install_command' => env('BACKUP_INSTALL_COMMAND', 'sudo /usr/local/bin/install_alis_authorized_keys.sh'),
    'process_timeout' => (int) env('BACKUP_DEPLOY_TIMEOUT', 30),
];
