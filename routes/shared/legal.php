<?php

use Illuminate\Support\Facades\Route;

Route::view('/terms-of-service', 'shared.legal.terms')->name('legal.terms');
Route::view('/privacy-policy', 'shared.legal.privacy')->name('legal.privacy');
