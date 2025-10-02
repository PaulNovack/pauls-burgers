<?php
namespace App\Services\Order\Dto;


final class RemoveById extends ParsedCommand
{
    public function __construct(
        public int $id,
        public int $qty,
        public ?string $size,
        /** @var string[] */ public array $needAdd,
        /** @var string[] */ public array $needRemove
    ) {}
}
