<?php

use App\Http\Controllers\MessagingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('message-center')->group(function () {
    Route::get('/', [MessagingController::class, 'index'])->name('messaging.index');
    Route::get('/inbox', [MessagingController::class, 'inbox'])->name('messaging.inbox');
    Route::get('/contacts', [MessagingController::class, 'contacts'])->name('messaging.contacts');
    Route::post('/start', [MessagingController::class, 'start'])->name('messaging.start');
    Route::get('/threads/{key}', [MessagingController::class, 'messages'])->name('messaging.messages');
    Route::post('/threads/{key}', [MessagingController::class, 'send'])->middleware('throttle:60,1')->name('messaging.send');
    Route::get('/attachments/{attachment}', [MessagingController::class, 'attachment'])->name('messaging.attachment');
});
