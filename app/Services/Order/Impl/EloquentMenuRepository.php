<?php
namespace App\Services\Order\Impl;


use App\Services\Order\Contracts\MenuRepository;


final class EloquentMenuRepository implements MenuRepository
{
    public function menu(): array
    {
        $items = \App\Models\Item::with('toppings')->get();
        if ($items->isEmpty()) return [];
        $out = [];
        foreach ($items as $it) {
            $out[$it->id] = [
                'id' => (int)$it->id,
                'name' => (string)$it->name,
                'type' => (string)$it->type,
                'category' => $it->category ?: null,
                'size' => $it->size ?: null,
                'price' => (float)$it->price,
                'toppings' => $it->toppings->pluck('name')->all() ?: null,
            ];
        }
        return $out;
    }
}
