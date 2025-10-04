<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TranscribeController;

Route::get('/transcribe', function () {
    return response()->json([
        'ok' => true,
        'message' => 'POST an audio file as multipart/form-data with field name "audio".'
    ]);
});

Route::get('/menu', function () {
    // You can also wrap this with Cache::remember if you like.
    return response()->json([
        'items' => config('menu.items', []),
    ]);
});

Route::post('/transcribe', TranscribeController::class);
