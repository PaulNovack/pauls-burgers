<?php

use App\Http\Controllers\ListFromAsrController;
use App\Http\Controllers\ListFromTextController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderFromAsrController;
use App\Http\Controllers\OrderFromTextController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::view('/', 'home');

Route::view('list', 'home');
Route::view('burgerorder', 'home');

Route::post('/audio/chunk', function (Request $request) {

    $path = storage_path('app/public/chunks');
    if (!is_dir($path)) mkdir($path, 0775, true);
    file_put_contents($path.'/'.uniqid('chunk_').'.webm', $request->getContent());

    return response()->json(['ok' => true]);
});


Route::get('/order', [OrderController::class, 'show']);
Route::post('/order/command', [OrderController::class, 'command']);
Route::post('/order/clear', [OrderController::class, 'clear']);
Route::post('/order/asr', OrderFromAsrController::class);

Route::post('/list/from-audio', ListFromAsrController::class); // expects: audio=<file>
Route::post('/list/from-text',  ListFromTextController::class)->withoutMiddleware([VerifyCsrfToken::class]);

Route::get('/{any}', function () {
    return view('home'); // the blade above
})->where('any', '^(?!api|storage|build|dist|assets|_debugbar).*$');


