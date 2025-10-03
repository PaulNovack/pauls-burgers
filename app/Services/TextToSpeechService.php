<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class TextToSpeechService
{
    /**
     * @var string Piper TTS endpoint (FastAPI) e.g., http://localhost:8001/speak
     */
    protected string $endpoint;

    /**
     * @var int HTTP timeout seconds
     */
    protected int $timeout;

    /**
     * @var Client
     */
    protected Client $http;

    public function __construct(?Client $http = null)
    {
        // You can set this in .env as PIPER_TTS_URL
        $this->endpoint = rtrim(config('services.piper_tts.url', env('PIPER_TTS_URL', 'http://127.0.0.1:8002/speak')), '/');
        $this->timeout  = (int) config('services.piper_tts.timeout', env('PIPER_TTS_TIMEOUT', 20));
        $this->http     = $http ?? new Client([
            'timeout' => $this->timeout,
            'http_errors' => false, // we’ll handle status codes ourselves
        ]);
    }

    /**
     * Get (or create) a TTS WAV from text.
     *
     * Returns a public URL like https://yourapp.test/wavs/{hash}.wav
     *
     * @param  string  $text
     * @return string public URL to WAV
     */
    public function getOrCreate(string $text): string
    {
        $clean = $this->normalizeText($text);
        if ($clean === '') {
            throw new RuntimeException('Text is empty after normalization.');
        }

        $hash = sha1($clean);
        $filename = "{$hash}.wav";
        $dir = public_path('wavs');
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        // Ensure directory exists
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // If file already exists, return public URL
        if (File::exists($path) && File::size($path) > 0) {
            return "http://127.0.0.1:8000/wavs/"  . $filename;
        }

        // Create it via Piper
        $wav = $this->synthesize($clean);

        if (! $wav || strlen($wav) === 0) {
            throw new RuntimeException('Piper returned empty audio.');
        }

        File::put($path, $wav);

        // Double-check write
        if (! File::exists($path) || File::size($path) === 0) {
            throw new RuntimeException('Failed to write WAV to disk.');
        }

        return "http://127.0.0.1:8000/wavs/" . $filename;
    }

    /**
     * Call Piper TTS server and return WAV bytes.
     *
     * @param  string  $text
     * @return string (binary) WAV data
     */
    protected function synthesize(string $text): string
    {
        try {
            $res = $this->http->post($this->endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode([
                    'text' => $text,
                    // If you want to force CLI fallback on your server:
                    // 'use_cli_fallback' => true
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to reach Piper TTS: ' . $e->getMessage(), 0, $e);
        }

        $status = $res->getStatusCode();
        if ($status !== 200) {
            // Try to surface FastAPI error if JSON
            $body = (string) $res->getBody();
            $detail = $this->extractDetail($body);
            throw new RuntimeException("Piper TTS error (HTTP {$status})" . ($detail ? ": {$detail}" : ''));
        }

        // Return raw audio bytes
        return (string) $res->getBody();
    }

    /**
     * Normalize text slightly (trim, collapse weird whitespace).
     */
    protected function normalizeText(string $text): string
    {
        $t = trim($text);
        // Normalize whitespace to single spaces (optional)
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        // Optional: hard-limit to your server’s max (e.g., 2000)
        return Str::limit($t, 2000, '');
    }

    /**
     * Try to extract {"detail": "..."} from FastAPI error bodies.
     */
    protected function extractDetail(string $body): ?string
    {
        if ($body === '') return null;
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['detail'])) {
            if (is_string($json['detail'])) return $json['detail'];
            return json_encode($json['detail']);
        }
        return null;
    }
}
