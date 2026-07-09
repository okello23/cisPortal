<?php

namespace App\Http\Controllers\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\AlisBackupConfiguration;
use App\Models\User;
use App\Support\AlisBackupScriptGenerator;
use App\Support\AuditService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class AlisRemoteBackupKeyController extends Controller
{
    public function index(): View
    {
        abort_unless($this->canManage(request()->user()), 403);

        return view('infrastructure.alis-remote-backup-keys.index');
    }

    public function downloadScript(
        Request $request,
        AlisBackupConfiguration $configuration,
        AlisBackupScriptGenerator $scriptGenerator,
        AuditService $auditService,
    ): StreamedResponse {
        abort_unless($this->canManage($request->user()), 403);
        abort_unless($configuration->status === AlisBackupConfiguration::STATUS_PROVISIONED, 409);

        $auditService->log(
            'downloaded_backup_script',
            $configuration,
            null,
            [
                'facility_id' => $configuration->facility_id,
                'backup_directory_name' => $configuration->backup_directory_name,
            ],
            $request->user()?->id,
            $request,
        );

        $filename = 'backupscript_'.$configuration->backup_directory_name.'.sh';

        return response()->streamDownload(
            fn () => print $scriptGenerator->render($configuration),
            $filename,
            ['Content-Type' => 'text/x-shellscript; charset=UTF-8']
        );
    }

    private function canManage(?User $user): bool
    {
        return $user?->hasAnyRole([
            User::ROLE_ICT_ADMIN,
            User::ROLE_ICT_SUPPORT_STAFF,
            User::ROLE_ICT_SUPERVISOR,
            User::ROLE_DEVELOPER,
        ]) ?? false;
    }
}
