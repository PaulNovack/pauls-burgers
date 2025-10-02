<?php
namespace App\Services\Order\Contracts;


interface MenuRepository
{
    /** @return array<int,array> keyed by id, each arr includes id,name,price,type,size,category,toppings? */
    public function menu(): array;
}
