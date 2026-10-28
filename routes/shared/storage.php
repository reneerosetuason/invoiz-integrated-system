<?php

use Illuminate\Support\Facades\Route;

// Public fallback route for storage assets (safely serves files if web server symlink fails)
Route::get('/storage/{path}', function ($path) {
    $clean = ltrim(str_replace(['\\', '/storage/'], ['/', ''], (string) $path), '/');
    $candidates = [
        public_path('storage/' . $clean),
        storage_path('app/public/' . $clean),
        public_path($clean),
        storage_path('app/' . $clean),
    ];
    foreach ($candidates as $cand) {
        if (file_exists($cand) && ! is_dir($cand)) {
            $mime = mime_content_type($cand) ?: 'application/octet-stream';
            return response()->file($cand, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . basename($cand) . '"',
            ]);
        }
    }
    abort(404, 'File not found');
})->where('path', '.*');
