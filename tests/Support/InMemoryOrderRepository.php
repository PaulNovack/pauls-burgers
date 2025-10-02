<?php


namespace Tests\Support;


use App\Services\Order\Contracts\OrderRepository;


final class InMemoryOrderRepository implements OrderRepository
{
    /** @var array<int,array> */
    private array $lines = [];


    public function all(): array { return $this->lines; }
    public function putAll(array $lines): void { $this->lines = $lines; }
    public function clear(): array { $this->lines = []; return []; }
}
