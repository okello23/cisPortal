<?php

use App\Http\Controllers\Api\SystemLookupController;
use App\Http\Controllers\Api\TicketApiController;
use Illuminate\Support\Facades\Route;

Route::get('/systems', [SystemLookupController::class, 'index']);
Route::get('/systems/{system}/modules', [SystemLookupController::class, 'modules']);
Route::post('/tickets', [TicketApiController::class, 'store']);
