<?php

namespace App\DataTransferObjects;

readonly class TranscriptionResult
{
    public function __construct(
        public string $text,
        public ?int $modelTimeMs,
        public float $endToEndMs,
        public bool $success = true,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'time_ms' => $this->modelTimeMs,
            'e2e_ms' => $this->endToEndMs,
            'success' => $this->success,
            'error' => $this->error,
        ];
    }
}
