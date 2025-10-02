<?php
namespace App\Services\Order\Impl;


use App\Services\Order\Contracts\MenuRepository;


final class ConfigMenuRepository implements MenuRepository
{
    public function menu(): array
    {
        $cfg = config('menu.items', []);
        $out = [];
        foreach ($cfg as $m) { $out[(int)$m['id']] = $m + ['id'=>(int)$m['id']]; }
        return $out;
    }
}
