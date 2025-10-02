<?php

// app/Services/Order/Contracts/ModifierResolver.php
namespace App\Services\Order\Contracts;

interface ModifierResolver
{
    /** @param string[] $raw */
    public function resolveList(array $raw): array;

    /** @param string[] $mods @return string[] filtered, canonical modifiers */
    public function filterByCategory(array $mods, ?string $category): array;
}
