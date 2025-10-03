<?php

namespace App\Http\Controllers;

use App\Services\AsrService;
use App\Services\Order\OrderService;   // ✅ import the modular service
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderFromAsrController extends Controller
{
    public function __invoke(Request $request, AsrService $asr, OrderService $order)
    {
        // Validate input (multipart/form-data with an audio file)
        $data = $request->validate([
            'audio' => ['required', 'file', 'mimes:wav,mp3,m4a,ogg,webm', 'max:30720'], // 30 MB
        ]);

        /** @var UploadedFile $file */
        $file = $data['audio'];

        try {
            // ASR service returns ['text' => string, 'time_ms' => int|null, 'e2e_ms' => int|null]
            $asrResult = $asr->transcribeUploadedFile($file);
            $text = trim((string)($asrResult['text'] ?? ''));

            // Use injected $order; DO NOT re-resolve with app()
            $result = $order->processCommand($text);

            return response()->json([
                'heard'    => $text,
                'action'   => $result['action'] ?? 'noop',
                'items'    => $result['items'] ?? [],
                'model_ms' => $asrResult['time_ms'] ?? null,
                'e2e_ms'   => $asrResult['e2e_ms'] ?? null,
                'tts_url'  => "lkjlkjkkljklafdlkdsalf.wav",
            ]);
        } catch (ValidationException $ve) {
            // will already return 422 via validate(), but keeping pattern here
            throw $ve;
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'error' => 'ASR or order processing failed',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
