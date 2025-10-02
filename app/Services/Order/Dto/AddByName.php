<?php
namespace App\Services\Order\Dto;


final class AddByName extends ParsedCommand
{
    public function __construct(
        public string $name,
        public int $qty,
        /** @var string[] */ public array $add,
        /** @var string[] */ public array $remove,
        public ?string $size
    ) {}
}
