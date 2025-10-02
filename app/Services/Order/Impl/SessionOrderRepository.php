<?php
namespace App\Services\Order\Impl;


use App\Services\Order\Contracts\OrderRepository;
use Illuminate\Contracts\Session\Session;


final class SessionOrderRepository implements OrderRepository
{
    public function __construct(
        private readonly Session $session,
        private readonly string $key = 'user.order.items',
    ) {}


    public function all(): array { return $this->session->get($this->key, []); }
    public function putAll(array $lines): void { $this->session->put($this->key, $lines); }
    public function clear(): array { $this->session->put($this->key, []); return []; }
}
