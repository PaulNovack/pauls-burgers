<?php
namespace App\Services\Order\Contracts;

interface ToppingPolicyRepository
{
    /**
     * Allowed toppings per category.
     * @return array{burger:string[], side:string[], drink:string[]}
     */
    public function all(): array;

    /** @return string[] */
    public function allowedFor(string $category): array;

    /**
     * Canonical → variants (all variants in lowercase).
     * Example: ['Cheddar Cheese' => ['cheddar','cheddar cheese']]
     * @return array<string,string[]>
     */
    public function getSynonyms(): array;
}
