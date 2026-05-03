<?php

declare(strict_types=1);

use App\Http\Controllers\WhitelistController;
use Illuminate\Support\Facades\Route;

Route::get('/whitelist.json', [WhitelistController::class, 'json'])->name('whitelist.json');
Route::get('/whitelist.txt', [WhitelistController::class, 'txt'])->name('whitelist.txt');
