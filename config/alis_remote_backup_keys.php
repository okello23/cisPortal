<?php

return [
    'allow_rsa' => (bool) env('ALIS_BACKUP_ALLOW_RSA', false),
    'host' => env('BACKUP_SERVER_HOST', env('ALIS_BACKUP_SERVER')),
    'port' => (int) env('BACKUP_SERVER_PORT', env('ALIS_BACKUP_PORT', 22)),
    'user' => env('BACKUP_SERVER_USER', env('ALIS_BACKUP_USER', 'backupuser')),
    'identity_file' => env('BACKUP_SSH_IDENTITY_FILE', env('ALIS_BACKUP_SSH_KEY')),
    'authorized_keys_path' => env('BACKUP_AUTHORIZED_KEYS_PATH'),
    'authorized_keys_owner' => env('BACKUP_AUTHORIZED_KEYS_OWNER'),
    'authorized_keys_group' => env('BACKUP_AUTHORIZED_KEYS_GROUP'),
    'authorized_keys_mode' => env('BACKUP_AUTHORIZED_KEYS_MODE'),
    'remote_temp_path' => env('BACKUP_REMOTE_TEMP_PATH'),
    'install_command' => env('BACKUP_INSTALL_COMMAND'),
    'process_timeout' => (int) env('BACKUP_DEPLOY_TIMEOUT', 30),
];
