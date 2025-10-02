<?php
namespace App\Services\Order\Impl;

use App\Services\Order\Contracts\ModifierResolver;
use App\Services\Order\Contracts\ToppingPolicyRepository;

final class DefaultModifierResolver implements ModifierResolver
{
    /** optional policy (config/db) */
    public function __construct(private ?ToppingPolicyRepository $policy = null) {}

    /** Map of synonyms → canonical names (trimmed for brevity; extend as needed) */
    private array $synonyms = [
        'Cheddar Cheese'  => ['cheddar','cheddar cheese','extra cheddar'],
        'Swiss Cheese'    => ['swiss','swiss cheese'],
        'American Cheese' => ['american','american cheese'],
        'Pepper Jack'     => ['pepper jack','pepperjack'],
        'Blue Cheese'     => ['blue cheese','bleu cheese','bleu'],
        'Bacon'           => ['bacon','crispy bacon'],
        'Onion'           => ['onion','onions','grilled onion','grilled onions'],

        // ⬇️ NEW: make “rings” a canonical mod and include “rinds” as a variant
        'Onion Rings'     => [
            'onion rings','onion ring','onion rngs','onion rng',
            'onion rinds','onion rind','onin rings','onin rinds'
        ],
        'Pickle'          => ['pickle','pickles'],
        'Tomato'          => ['tomato','tomatoes'],
        'Lettuce'         => ['lettuce'],
        'Jalapeno'        => ['jalapeno','jalapenos','jalapeño','jalapeños'],
        'Ketchup'         => ['ketchup'],
        'Mustard'         => ['mustard','yellow mustard'],
        'Mayo'            => ['mayo','mayonnaise'],
        'BBQ Sauce'       => ['bbq','bbq sauce','barbecue','barbeque'],
        'Ice'             => ['ice','with ice','no ice','without ice'], // canonical stays 'Ice'
        // NEW: make “Ranch Dressing” resolvable (and catch common typos)
        'Ranch Dressing'  => [
            'ranch', 'ranch dressing', 'ranch sauce',
            'ranch dresing', 'ranch drssing', 'ranch drsg', 'ranch trusting'
        ],
        'Thousand Island Dressing' => ['thousand island dressing','thousand island','1000 island'],
    ];

    /** Canonicalize + dedupe */
    public function resolveList(array $raw): array
    {
        $out=[]; $seen=[];
        foreach ($raw as $frag) {
            $can = $this->canonicalize($frag);
            if ($can==='') continue;
            $k = mb_strtolower($can);
            if (isset($seen[$k])) continue;
            $seen[$k]=true; $out[]=$this->title($can);
        }
        return $out;
    }

    /** ✅ The method your tests call (array first, category second) */
    public function filterByCategory(array $mods, ?string $category): array
    {
        if (empty($mods)) return [];
        $cat = $this->normalizeCategory($category);

        // ✅ If category is unknown, do NOT filter — just canonicalize.
        if ($cat === null) {
            return $this->resolveList($mods);
        }

        $allowed = $this->policy?->allowedFor($cat) ?? $this->defaultsFor($cat);

        // If we somehow don’t have an allow-list, also skip filtering.
        if ($allowed === null) {
            return $this->resolveList($mods);
        }

        $allowedMap = [];
        foreach ($allowed as $a) $allowedMap[mb_strtolower($a)] = true;

        $resolved = $this->resolveList($mods);
        return array_values(array_filter(
            $resolved,
            fn($m) => isset($allowedMap[mb_strtolower($m)])
        ));
    }

    // ---------- helpers ----------

    private function canonicalize(string $s): string
    {
        $raw = mb_strtolower(trim($s));
        if ($raw==='') return '';

        $title = $this->title($s);

        // exact canonical name match
        foreach (array_keys($this->synonyms) as $canon) {
            if ($title === $canon) return $canon;
        }

        // exact variant match
        foreach ($this->synonyms as $canon => $variants) {
            foreach ($variants as $v) {
                if ($raw === mb_strtolower($v)) return $canon;
            }
        }

        // light fuzzy match across variants + canonicals
        $best = '';
        $bestDist = PHP_INT_MAX;

        foreach ($this->synonyms as $canon => $variants) {
            foreach ($variants as $v) {
                $d = levenshtein($raw, mb_strtolower($v));
                if ($d < $bestDist) { $bestDist = $d; $best = $canon; }
            }
            $d2 = levenshtein($raw, mb_strtolower($canon));
            if ($d2 < $bestDist) { $bestDist = $d2; $best = $canon; }
        }

        // accept only very close matches (e.g., "trusting" -> "dressing")
        if ($best !== '' && $bestDist <= 3) return $best;

        return $title;
    }

    private function normalizeCategory(?string $c): ?string
    {
        $x = mb_strtolower(trim((string)$c));
        return match ($x) {
            'burger','burgers','sandwich' => 'burger',
            'side','sides','app','apps','appetizer','appetizers' => 'side',
            'drink','drinks','beverage','beverages' => 'drink',
            default => null,   // ✅ don’t guess; unknown => no filtering
        };
    }

    private function defaultsFor(string $cat): ?array
    {
        return match ($cat) {
            'burger' => [
                'Cheddar Cheese','Swiss Cheese','American Cheese','Pepper Jack','Blue Cheese',
                'Bacon','Onion','Pickle','Tomato','Lettuce','Jalapeno','Ketchup','Mustard','Mayo','BBQ Sauce'
            ],
            'side'   => ['Ketchup','BBQ Sauce','Mayo','Ranch','Cheese Sauce','Jalapeno'],
            'drink'  => ['Ice'],
            default  => null,
        };
    }

    private function title(string $s): string
    { return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8'); }
}
