<?php
namespace App\Services\Order\Impl;

use App\Services\Order\Contracts\ToppingPolicyRepository;

final class ConfigToppingPolicyRepository implements ToppingPolicyRepository
{
    private ?array $allowed = null;  // cache
    private ?array $syn     = null;  // cache

    public function all(): array
    {
        return $this->allowed ??= [
            'burger' => array_values(config('menu.toppings.burger', [])),
            'side'   => array_values(config('menu.toppings.side', [])),
            'drink'  => array_values(config('menu.toppings.drink', [])),
        ];
    }

    public function allowedFor(string $category): array
    {
        $key = mb_strtolower(trim($category));
        $all = $this->all();
        return $all[$key] ?? [];
    }

    public function getSynonyms(): array
    {
        if ($this->syn !== null) {
            return $this->syn;
        }

        // Optional config key for explicit variants
        // e.g. 'menu.topping_synonyms' => ['Cheddar Cheese' => ['cheddar','cheddar cheese']]
        $cfg = config('menu.topping_synonyms', []);

        $map = [];
        foreach ($this->all() as $list) {
            foreach ($list as $canon) {
                $canonTitle = $this->title($canon);
                $lcCanon    = mb_strtolower($canonTitle);
                $variants   = $cfg[$canonTitle] ?? $cfg[$lcCanon] ?? [];
                $variants   = array_map(fn($v) => mb_strtolower($v), $variants);

                // ensure at least itself + a naive singular/plural flip
                $variants = array_unique(array_merge([$lcCanon], $variants, $this->autoInflect($lcCanon)));

                $map[$canonTitle] = array_values($variants);
            }
        }

        // Make sure drink policy always recognizes "ice"
        if (!isset($map['Ice'])) {
            $map['Ice'] = ['ice'];
        }

        return $this->syn = $map;
    }

    private function autoInflect(string $lc): array
    {
        $out = [];
        if (preg_match('/(.*[^aeiou])ies$/u', $lc, $m)) {
            $out[] = $m[1].'y';
        } elseif (preg_match('/(.*)s$/u', $lc, $m)) {
            $out[] = $m[1];
        } else {
            $out[] = $lc.'s';
        }
        return $out;
    }

    private function title(string $s): string
    {
        return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8');
    }
}
