<?php

declare(strict_types=1);

use App\Http\Controllers\WhitelistController;
use App\Http\Middleware\EnsureApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureApiToken::class])->group(function () {
    Route::post('/whitelist', [WhitelistController::class, 'apiAdd']);
});

Route::get('/whitelist/{uuid}', [WhitelistController::class, 'apiCheck']);
