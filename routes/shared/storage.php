<?php

use App\Models\Seller;
use App\Support\AssetFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Only public image files, confined to the public asset roots.
Route::get('/storage/{path}', function (string $path) {
    $file = AssetFiles::publicFile($path);
    abort_unless($file, 404);
    return response()->file($file, ['X-Content-Type-Options' => 'nosniff']);
})->where('path', '.*');

// Account identity/tax documents are never publicly addressable.
Route::get('/documents/{path}', function (Request $request, string $path) {
    $clean = AssetFiles::clean($path);
    abort_unless($clean !== null && AssetFiles::isPrivate($clean), 404);
    $user = $request->user();
    $ownsDocument = $user->id_image === $clean || Seller::where('user_id', $user->id)
        ->where(fn ($q) => $q->where('id_image', $clean)->orWhere('dti_certificate', $clean)
            ->orWhere('bir_2303', $clean)->orWhere('business_permit', $clean))->exists();
    abort_unless($user->isAdmin() || $ownsDocument, 404);
    $file = AssetFiles::document($clean);
    abort_unless($file, 404);
    return response()->file($file, [
        'Cache-Control' => 'private, no-store',
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "sandbox; default-src 'none'",
    ])->setPrivate();
})->middleware(['auth:sanctum', 'account.active'])->where('path', '.*')->name('documents.show');
