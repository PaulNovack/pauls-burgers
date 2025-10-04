<?php

namespace App\Exceptions;

class AsrServiceException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function serviceUnavailable(int $statusCode, string $body): self
    {
        return new self(
            "ASR service returned status {$statusCode}",
            $statusCode,
            $body
        );
    }

    public static function invalidResponse(string $reason): self
    {
        return new self("Invalid ASR response: {$reason}");
    }

    public static function fileError(string $reason, ?\Throwable $previous = null): self
    {
        return new self("File operation failed: {$reason}", previous: $previous);
    }
}
