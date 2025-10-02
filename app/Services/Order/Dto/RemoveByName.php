<?php
namespace App\Services\Order\Dto;


final class RemoveByName extends ParsedCommand
{
    public function __construct(
        public string $name,
        public int $qty,
        public ?string $size,
        /** @var string[] */ public array $needAdd,
        /** @var string[] */ public array $needRemove
    ) {}
}
