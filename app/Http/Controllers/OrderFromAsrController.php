<?php

namespace App\Http\Controllers;

use App\Services\AsrService;
use App\Services\Order\OrderService;   // ✅ import the modular service
use App\Services\TextToSpeechService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderFromAsrController extends Controller
{

    /**
     * Return a randomized short reply for unrecognized input or not-on-menu items.
     *
     * @param string      $heard   The phrase we think we heard (may be empty).
     * @param null|string $reason  Optional: 'not_understood' | 'not_on_menu' | null
     */
    private function randomNoMatchReply(string $heard, ?string $reason = null): string {
        // Light cleanup for TTS friendliness
        $heard = trim(preg_replace('/\s+/', ' ', $heard));
        $heard = rtrim($heard, ".!?");
        $heardOut = $heard !== '' ? $heard : 'that';

        $templates = [
            'not_understood' => [
                "I didn't catch that. I heard: {heard}.",
                "Sorry, I couldn't understand. I heard: {heard}.",
                "I missed that. I heard: {heard}.",
                "I couldn’t make that out. I heard: {heard}.",
                "Pardon, I didn’t follow. I heard: {heard}.",
                "Not sure I got that. I heard: {heard}.",
            ],
            'not_on_menu' => [
                "That’s not on the menu or order. I heard: {heard}.",
                "No menu or order match. I heard: {heard}.",
                "I can’t find that item. I heard: {heard}.",
                "Item unavailable. I heard: {heard}.",
                "I don’t see that on the menu or your order. I heard: {heard}.",
                "Menu or order item not found. I heard: {heard}.",
            ],
            'generic' => [
                "Let’s try again. I heard: {heard}.",
                "Please rephrase. I heard: {heard}.",
                "Mind repeating? I heard: {heard}.",
                "Try a menu item name. I heard: {heard}.",
                "Maybe try something like ‘Large fries’. I heard: {heard}.",
            ],
        ];

        // Build a pool based on reason (or mix all if unknown)
        if ($reason === 'not_understood') {
            $pool = array_merge($templates['generic'], $templates['not_understood']);
        } elseif ($reason === 'not_on_menu') {
            $pool = array_merge($templates['generic'], $templates['not_on_menu']);
        } else {
            $pool = array_merge($templates['generic'], $templates['not_understood'], $templates['not_on_menu']);
        }

        $tpl = $pool[random_int(0, count($pool) - 1)];
        return str_replace('{heard}', $heardOut, $tpl);
    }
    private function randomOkPhrase(): string {
        static $phrases = [
            'OK',
            'you bet',
            'Okay',
            'with pleasure',
            'as you wish',
            'got it',
            'Sure',
            'Done',
            'Sure thing',
            'right away',
            'ok Done',
            'ok that Works',
            'Got it',
            'On it',
            'Alright',
            'A OK',
            'for sure',
            'yes sir',
            'no problem',
        ];

        return $phrases[random_int(0, count($phrases) - 1)];
    }
    public function __invoke(Request $request, AsrService $asr, OrderService $order,TextToSpeechService $tts)
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
            Log::channel("phrase",)->info("ASR Text:",[$text]);
            // Use injected $order; DO NOT re-resolve with app()
            $result = $order->processCommand($text);
            if($result['action'] != 'add' && $result['action'] != 'remove' && $text != ''){
                $wav =$tts->getOrCreate($this->randomNoMatchReply($text));
                Log::channel("phrase",)->info("ASR Response:",['failed']);
            } else if ($text != '' && $text != 'thank you'){
                $wav =$tts->getOrCreate($this->randomOkPhrase());
                Log::channel("phrase",)->info("ASR Response:",['success']);
            }

            return response()->json([
                'heard'    => $text,
                'action'   => $result['action'] ?? 'noop',
                'items'    => $result['items'] ?? [],
                'model_ms' => $asrResult['time_ms'] ?? null,
                'e2e_ms'   => $asrResult['e2e_ms'] ?? null,
                'tts_url'  => $wav,
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
