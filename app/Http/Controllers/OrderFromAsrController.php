<?php

namespace App\Http\Controllers;

use App\Services\AsrService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderFromAsrController extends Controller
{
    public function __invoke(Request $request, AsrService $asr, OrderService $order)
    {
        if (!$request->hasFile('audio')) {
            return response()->json(['error' => 'No audio uploaded'], 400);
        }

        $file = $request->file('audio');
        $asrResult = $asr->transcribeUploadedFile($file); // ['text','time_ms','e2e_ms']
        $text = $asrResult['text'] ?? '';

        $result = $order->processCommand($text); // ['action','items']

        return response()->json([
            'heard'    => $text,
            'action'   => $result['action'],
            'items'    => $result['items'],        // session order lines
            'model_ms' => $asrResult['time_ms'] ?? null,
            'e2e_ms'   => $asrResult['e2e_ms'] ?? null,
        ]);
    }
}
