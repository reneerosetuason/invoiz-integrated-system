<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Buyer\BuyerChatController;

Route::get('/buyer/login', [BuyerChatController::class, 'showLogin'])->name('buyer.login');
Route::post('/buyer/login', [BuyerChatController::class, 'login'])->name('buyer.login.post');
Route::post('/buyer/logout', [BuyerChatController::class, 'logout'])->name('buyer.logout');

Route::prefix('buyer')->middleware('buyer')->group(function () {
    Route::get('/chat', [BuyerChatController::class, 'chat'])->name('buyer.chat');
    Route::post('/chat/send', [BuyerChatController::class, 'send'])->name('buyer.chat.send');
    Route::get('/chat/{user}/messages', [BuyerChatController::class, 'messages'])->name('buyer.chat.messages');
});
