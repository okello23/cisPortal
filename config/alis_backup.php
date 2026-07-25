<?php

return [
    'server' => env('BACKUP_SERVER_HOST'),
    'user' => env('BACKUP_SERVER_USER', 'backupuser'),
    'port' => env('BACKUP_SERVER_PORT', 22),
    'root' => env('BACKUP_SERVER_ROOT', env('ALIS_BACKUP_ROOT', '/dumps')),
    'ssh_key' => env('BACKUP_SSH_IDENTITY_FILE', env('ALIS_BACKUP_SSH_KEY')),
    'facility_offsite_server_ip' => env('FACILITY_OFFSITE_SERVER_IP'),
];
