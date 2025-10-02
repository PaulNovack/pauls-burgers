<?php
namespace App\Services\Order\Contracts;

interface ModifierResolver
{
    /** Canonicalize & dedupe raw phrases (no category filtering). */
    public function resolveList(array $raw): array;

    /**
     * Keep only modifiers allowed for the given category/type.
     * $categoryOrType examples: "Burger", "Side", "Drink" (case-insensitive).
     */
    public function filterByCategory(string $categoryOrType, array $mods): array;
}
