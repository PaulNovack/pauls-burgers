<?php

// app/Services/Order/Impl/EloquentToppingPolicyRepository.php
namespace App\Services\Order\Impl;

use App\Models\Topping;
use App\Services\Order\Contracts\ToppingPolicyRepository;

final class EloquentToppingPolicyRepository implements ToppingPolicyRepository
{
    public function getAllowedFor(string $category): array
    {
        $key = mb_strtolower(trim($category));
        $map = [
            'burger'=>'burger','burgers'=>'burger','sandwich'=>'burger','sandwiches'=>'burger',
            'side'=>'side','sides'=>'side','app'=>'side','apps'=>'side','appetizer'=>'side','appetizers'=>'side',
            'drink'=>'drink','drinks'=>'drink','beverage'=>'drink','beverages'=>'drink','soda'=>'drink','coke'=>'drink',
        ];
        $norm = $map[$key] ?? $key;

        return Topping::query()
            ->whereJsonContains('allowed_for', $norm)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function getSynonyms(): array
    {
        // Build from DB: canonical => synonyms[]
        $out = [];
        Topping::query()->select(['name','synonyms'])->chunk(200, function ($rows) use (&$out) {
            foreach ($rows as $row) {
                $syn = is_array($row->synonyms) ? $row->synonyms : [];
                $out[$row->name] = array_values(array_unique(array_map('strval', $syn)));
            }
        });
        return $out;
    }
}
