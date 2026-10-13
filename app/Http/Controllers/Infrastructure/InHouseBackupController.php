<?php

namespace App\Http\Controllers\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\InHouseBackupConfiguration;
use App\Models\User;
use App\Support\InHouseBackupScriptGenerator;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InHouseBackupController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request->user());
        return view('infrastructure.in-house-backups.index');
    }

    public function downloadScript(Request $request, InHouseBackupConfiguration $configuration, InHouseBackupScriptGenerator $generator): StreamedResponse
    {
        $this->authorizeAccess($request->user());
        abort_unless($configuration->status === 'active', 409);
        return response()->streamDownload(fn () => print $generator->render($configuration), 'backupscript.sh', ['Content-Type' => 'text/x-shellscript; charset=UTF-8']);
    }

    private function authorizeAccess(?User $user): void
    {
        abort_unless($user?->hasAnyRole([User::ROLE_ICT_ADMIN, User::ROLE_ICT_SUPPORT_STAFF, User::ROLE_ICT_SUPERVISOR, User::ROLE_DEVELOPER]), 403);
    }
}
