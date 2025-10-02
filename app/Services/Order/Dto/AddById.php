<?php
namespace App\Services\Order\Dto;


final class AddById extends ParsedCommand
{
    public function __construct(
        public int $id,
        public int $qty,
        /** @var string[] */ public array $add,
        /** @var string[] */ public array $remove
    ) {}
}
