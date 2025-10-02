<?php
namespace App\Services\Order\Impl;


use App\Services\Order\Contracts\MenuRepository;


/** DB first, then config fallback */
final class CompoundMenuRepository implements MenuRepository
{
    public function __construct(
        private readonly EloquentMenuRepository $db,
        private readonly ConfigMenuRepository $cfg,
    ) {}


    public function menu(): array
    {
        $db = $this->db->menu();
        return !empty($db) ? $db : $this->cfg->menu();
    }
}
