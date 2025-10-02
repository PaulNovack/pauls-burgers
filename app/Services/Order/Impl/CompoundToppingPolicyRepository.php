<?php
namespace App\Services\Order\Impl;

use App\Services\Order\Contracts\ToppingPolicyRepository;

final class CompoundToppingPolicyRepository implements ToppingPolicyRepository
{
    public function __construct(
        private readonly ToppingPolicyRepository $dbRepo,   // e.g. EloquentToppingPolicyRepository
        private readonly ToppingPolicyRepository $cfgRepo,  // ConfigToppingPolicyRepository
    ) {}

    public function all(): array
    {
        $db = $this->dbRepo->all();
        // consider DB “empty” if all lists are empty
        $empty = !array_filter($db, fn($v) => !empty($v));
        return $empty ? $this->cfgRepo->all() : $db;
    }

    public function allowedFor(string $category): array
    {
        return $this->all()[strtolower($category)] ?? [];
    }
}
