<?php

namespace App\Services;

use App\DataTransferObjects\TranscriptionResult;
use App\Exceptions\AsrServiceException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AsrService
{
    private string $route;
    private string $baseUrl;
    private int $timeoutSeconds;
    private int $maxFileSize;
    private array $allowedExtensions;
    private array $allowedMimeTypes;
    private bool $retryEnabled;
    private int $retryTimes;
    private int $retrySleepMs;

    public function __construct()
    {
        $this->baseUrl = config('asr.url');
        $this->route = config('asr.route');
        $this->timeoutSeconds = config('asr.timeout');
        $this->maxFileSize = config('asr.max_file_size');
        $this->allowedExtensions = config('asr.allowed_extensions');
        $this->allowedMimeTypes = config('asr.allowed_mime_types');
        $this->retryEnabled = config('asr.retry.enabled');
        $this->retryTimes = config('asr.retry.times');
        $this->retrySleepMs = config('asr.retry.sleep_ms');
    }

    /**
     * Accepts an UploadedFile, validates, stores it transiently, calls ASR, and cleans up.
     *
     * @throws AsrServiceException
     */
    public function transcribeUploadedFile(UploadedFile $file): TranscriptionResult
    {
        $this->validateFile($file);

        $path = $this->storeFile($file);
        $fullPath = Storage::disk('local')->path($path);

        try {
            return $this->transcribePath($fullPath);
        } finally {
            $this->cleanupFile($fullPath, $path);
        }
    }

    /**
     * Calls the ASR endpoint with a local file path.
     *
     * @throws AsrServiceException
     */
    public function transcribePath(string $fullPath): TranscriptionResult
    {
        if (!file_exists($fullPath)) {
            throw AsrServiceException::fileError("File not found: {$fullPath}");
        }

        if (!is_readable($fullPath)) {
            throw AsrServiceException::fileError("File not readable: {$fullPath}");
        }

        $url = $this->baseUrl . $this->route;
        $start = microtime(true);

        try {
            $response = $this->makeRequest($fullPath, $url);
            $e2eMs = (microtime(true) - $start) * 1000;

            if ($response->failed()) {
                $this->logError($response, $e2eMs, $url);
                throw AsrServiceException::serviceUnavailable(
                    $response->status(),
                    $response->body()
                );
            }

            return $this->parseResponse($response->json() ?? [], $e2eMs);
        } catch (AsrServiceException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('ASR: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw AsrServiceException::fileError($e->getMessage(), $e);
        }
    }

    /**
     * Validate the uploaded file meets requirements.
     *
     * @throws AsrServiceException
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            $maxMb = round($this->maxFileSize / 1024 / 1024, 2);
            throw AsrServiceException::fileError(
                "File size exceeds maximum allowed size of {$maxMb}MB"
            );
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions, true)) {
            throw AsrServiceException::fileError(
                "File extension '{$extension}' not allowed. Allowed: " .
                implode(', ', $this->allowedExtensions)
            );
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw AsrServiceException::fileError(
                "MIME type '{$mimeType}' not allowed"
            );
        }

        // Validate it's actually a file
        if (!$file->isValid()) {
            throw AsrServiceException::fileError('Uploaded file is not valid');
        }
    }

    /**
     * Store the file transiently.
     *
     * @throws AsrServiceException
     */
    private function storeFile(UploadedFile $file): string
    {
        try {
            $filename = uniqid('asr_', true) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('transient', $filename, 'local');

            if (!$path) {
                throw new \RuntimeException('Failed to store file');
            }

            Log::info('ASR: file stored', [
                'path' => $path,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]);

            return $path;
        } catch (\Throwable $e) {
            throw AsrServiceException::fileError(
                'Failed to store uploaded file: ' . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Make HTTP request to ASR service with optional retry.
     */
    private function makeRequest(string $fullPath, string $url): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        $maxAttempts = $this->retryEnabled ? $this->retryTimes + 1 : 1;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = Http::timeout($this->timeoutSeconds)
                    ->attach(
                        'audio',
                        file_get_contents($fullPath),
                        basename($fullPath)
                    )
                    ->post($url);

                // If successful or client error (4xx), don't retry
                if ($response->successful() || $response->clientError()) {
                    return $response;
                }

                // Server error - may retry
                if ($attempt < $maxAttempts) {
                    Log::warning('ASR: retrying request', [
                        'attempt' => $attempt,
                        'status' => $response->status(),
                    ]);
                    usleep($this->retrySleepMs * 1000);
                    continue;
                }

                return $response;
            } catch (\Throwable $e) {
                if ($attempt < $maxAttempts) {
                    Log::warning('ASR: retrying after exception', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    usleep($this->retrySleepMs * 1000);
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException('Failed to make ASR request after retries');
    }

    /**
     * Parse and validate the ASR response.
     *
     * @throws AsrServiceException
     */
    private function parseResponse(array $json, float $e2eMs): TranscriptionResult
    {
        if (!isset($json['text'])) {
            throw AsrServiceException::invalidResponse('Missing "text" field');
        }

        $text = trim((string)$json['text']);
        $modelTimeMs = isset($json['time_ms']) ? (int)$json['time_ms'] : null;

        Log::info('ASR: transcription complete', [
            'text_length' => mb_strlen($text),
            'text_preview' => mb_substr($text, 0, 80),
            'model_ms' => $modelTimeMs,
            'e2e_ms' => round($e2eMs, 1),
        ]);

        return new TranscriptionResult(
            text: $text,
            modelTimeMs: $modelTimeMs,
            endToEndMs: round($e2eMs, 1),
        );
    }

    /**
     * Clean up the temporary file.
     */
    private function cleanupFile(string $fullPath, string $storagePath): void
    {
        try {
            if (file_exists($fullPath)) {
                if (!unlink($fullPath)) {
                    Log::warning('ASR: failed to delete temp file', [
                        'path' => $fullPath,
                    ]);
                }
            }

            // Also try via Storage facade
            if (Storage::disk('local')->exists($storagePath)) {
                Storage::disk('local')->delete($storagePath);
            }
        } catch (\Throwable $e) {
            Log::error('ASR: cleanup error', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log detailed error information.
     */
    private function logError(
        \Illuminate\Http\Client\Response $response,
        float $e2eMs,
        string $url
    ): void {
        Log::error('ASR: service error', [
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
            'e2e_ms' => round($e2eMs, 1),
            'asr_url' => $url,
            'headers' => $response->headers(),
        ]);
    }
}
