<?php

namespace App\Http\Controllers\Infrastructure;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AlisRemoteBackupKeyController extends Controller
{
    public function index(): View
    {
        return view('infrastructure.alis-remote-backup-keys.index');
    }
}
