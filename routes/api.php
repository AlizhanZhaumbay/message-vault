<?php

use App\Http\Controllers\DownloadAttachmentController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PreviewMessageController;
use App\Http\Controllers\ShowStatsController;
use App\Http\Middleware\EnsureOwnerToken;
use Illuminate\Support\Facades\Route;

Route::pattern('message', '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}');
Route::pattern('attachment', '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}');

Route::apiResource('messages', MessageController::class)->only(['index', 'store', 'show']);

Route::middleware(EnsureOwnerToken::class)->group(function () {
    Route::get('messages/{message}/preview', PreviewMessageController::class);
    Route::apiResource('messages', MessageController::class)->only(['update', 'destroy']);
});

Route::get('attachments/{attachment}/download', DownloadAttachmentController::class)
    ->middleware('signed')
    ->name('attachments.download');

Route::get('stats', ShowStatsController::class);
