<?php
namespace App\Services\Order\Impl;

use App\Services\Order\Contracts\ModifierResolver;

final class DefaultModifierResolver implements ModifierResolver
{
    /** Canonical synonyms (category-agnostic canonicalization) */
    private array $synonyms = [
        // cheeses
        'Cheddar Cheese'  => ['cheddar','cheddar cheese','extra cheddar'],
        'Swiss Cheese'    => ['swiss','swiss cheese'],
        'American Cheese' => ['american','american cheese'],
        'Pepper Jack'     => ['pepper jack','pepperjack'],
        'Blue Cheese'     => ['blue cheese','bleu cheese','bleu'],
        // common toppings
        'Bacon'           => ['bacon','crispy bacon'],
        'Onion'           => ['onion','onions','grilled onion','grilled onions'],
        'Pickle'          => ['pickle','pickles'],
        'Tomato'          => ['tomato','tomatoes'],
        'Lettuce'         => ['lettuce'],
        'Jalapeno'        => ['jalapeno','jalapenos','jalapeño','jalapeños'],
        // sauces
        'Ketchup'         => ['ketchup'],
        'Mustard'         => ['mustard','yellow mustard'],
        'Mayo'            => ['mayo','mayonnaise'],
        'BBQ Sauce'       => ['bbq','bbq sauce','barbecue','barbeque'],
        // drinks
        'Ice'             => ['ice'],
    ];

    /** Allowed sets by category/type */
    private array $allowedByCategory = [
        'burger' => [
            'Cheddar Cheese','Swiss Cheese','American Cheese','Pepper Jack','Blue Cheese',
            'Bacon','Onion','Pickle','Tomato','Lettuce','Jalapeno',
            'Ketchup','Mustard','Mayo','BBQ Sauce',
            // (no Ice on burgers)
        ],
        'side' => [
            // typical side dips/condiments; adjust to taste
            'Ketchup','Mustard','Mayo','BBQ Sauce',
            // If you allow cheese on sides, uncomment:
            // 'Cheddar Cheese','Blue Cheese',
        ],
        'drink' => [
            'Ice', // drinks allow only Ice (so "without ice" works)
        ],
    ];

    public function resolveList(array $raw): array
    {
        $out=[]; $seen=[];
        foreach ($raw as $frag) {
            $can = $this->canonicalize($frag);
            if ($can==='') continue;
            $k = mb_strtolower($can);
            if (isset($seen[$k])) continue;
            $seen[$k]=true;
            $out[]=$this->title($can);
        }
        return $out;
    }

    public function filterByCategory(string $categoryOrType, array $mods): array
    {
        $key = $this->keyForCategory($categoryOrType);
        $allowed = $this->allowedByCategory[$key] ?? null;

        // If we don't know the category, default to legacy behavior (keep all).
        if ($allowed === null) return $mods;

        $allowedSet = [];
        foreach ($allowed as $a) { $allowedSet[mb_strtolower($a)] = true; }

        $out=[];
        foreach ($mods as $m) {
            if (isset($allowedSet[mb_strtolower($m)])) $out[] = $m;
        }
        return $out;
    }

    private function keyForCategory(string $c): string
    {
        $x = mb_strtolower(trim($c));
        // normalize some common values from your menu data
        return match ($x) {
            'burger','burgers','sandwich','sandwiches' => 'burger',
            'side','sides','app','apps','appetizer','appetizers' => 'side',
            'drink','drinks','beverage','beverages','soda','coke' => 'drink',
            default => $x, // unknown keys will bypass filtering
        };
    }

    private function canonicalize(string $s): string
    {
        $raw = mb_strtolower(trim($s));
        if ($raw==='') return '';
        $title = $this->title($s);

        // exact canonical
        foreach (array_keys($this->synonyms) as $canon) if ($title === $canon) return $canon;

        // exact synonym
        foreach ($this->synonyms as $canon=>$variants) {
            foreach ($variants as $v) if ($raw === mb_strtolower($v)) return $canon;
        }

        // light fuzzy (small typos)
        $best=''; $bestDist=3;
        foreach ($this->synonyms as $canon=>$variants) {
            foreach ($variants as $v) {
                $d=levenshtein($raw, mb_strtolower($v));
                if ($d < $bestDist) { $bestDist=$d; $best=$canon; }
            }
            $d2=levenshtein($raw, mb_strtolower($canon));
            if ($d2 < $bestDist) { $bestDist=$d2; $best=$canon; }
        }
        return $best !== '' ? $best : $title;
    }

    private function title(string $s): string
    {
        return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8');
    }
}
