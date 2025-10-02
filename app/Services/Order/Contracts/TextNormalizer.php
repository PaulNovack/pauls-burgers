<?php
namespace App\Services\Order\Contracts;


interface TextNormalizer
{
    public function normalizeCommand(string $s): string; // your old normalize()
    public function normalizeSize(?string $size): ?string; // Small/Regular/Large
    public function normName(string $s): string; // compare-friendly
    public function stripDiacritics(string $s): string;
}
