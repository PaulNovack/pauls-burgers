<?php
namespace App\Services\Order\Contracts;


interface OrderRepository
{
    /** @return array<int,array> lines */
    public function all(): array;
    /** Replace all lines */
    public function putAll(array $lines): void;
    /** Clear and return [] */
    public function clear(): array;
}
