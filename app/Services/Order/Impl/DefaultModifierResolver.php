<?php
namespace App\Services\Order\Impl;

use App\Services\Order\Contracts\ModifierResolver;

final class DefaultModifierResolver implements ModifierResolver
{
    /** @var array<string,string[]> */
    private array $synonyms = [
        // cheeses / basics
        'Cheddar Cheese'          => ['cheddar','cheddar cheese','extra cheddar'],
        'Swiss Cheese'            => ['swiss','swiss cheese'],
        'American Cheese'         => ['american','american cheese'],
        'Pepper Jack'             => ['pepper jack','pepperjack'],
        'Bacon'                   => ['bacon','crispy bacon'],
        'Onion'                   => ['onion','onions','grilled onion','grilled onions'],
        'Pickle'                  => ['pickle','pickles'],
        'Tomato'                  => ['tomato','tomatoes'],
        'Lettuce'                 => ['lettuce'],
        'Jalapeno'                => ['jalapeno','jalapenos','jalapeño','jalapeños'],
        'Ketchup'                 => ['ketchup'],
        'Mustard'                 => ['mustard','yellow mustard'],
        'Mayo'                    => ['mayo','mayonnaise'],
        'BBQ Sauce'               => ['bbq','bbq sauce','barbecue','barbeque'],
        'Ice'                     => ['ice'],

        'Blue Cheese' => ['blue cheese','bleu cheese','bleu'],
        'Ranch Dressing' => ['ranch dressing','ranch','ranch dressin','ranch trusting'],
        'Thousand Island Dressing' => [
            'thousand island dressing','thousand island','1000 island','thousand islands'
        ],
    ];

    /** lazy-built index of variant->canonical */
    /** @var array<string,string>|null */
    private ?array $variantMap = null;

    /** giant regex of all variants, word-boundary anchored */
    private ?string $variantPattern = null;

    public function resolveList(array $raw): array
    {
        $this->ensureIndex();

        $out = [];
        $seen = [];

        foreach ($raw as $frag) {
            $frag = trim((string)$frag);
            if ($frag === '') continue;

            // 1) Pull all *known variants* that appear inside this fragment.
            $hits = $this->extractKnowns($frag);

            if ($hits) {
                foreach ($hits as $canon) {
                    $k = mb_strtolower($canon);
                    if (isset($seen[$k])) continue;
                    $seen[$k] = true;
                    $out[] = $this->title($canon);
                }
                continue;
            }

            // 2) Fallback: try to interpret the whole fragment fuzzily.
            $canon = $this->canonicalize($frag);
            if ($canon !== '') {
                $k = mb_strtolower($canon);
                if (!isset($seen[$k])) {
                    $seen[$k] = true;
                    $out[] = $this->title($canon);
                }
            }
        }

        return $out;
    }

    /**
     * Try to find multiple toppings inside a single fragment using the
     * known-variant regex (handles "Thousand Island ketchup mustard")
     * and partial matches like "Ranch Trusting" → "Ranch" → Ranch Dressing.
     *
     * @return string[] canonical names found, in order
     */
    private function extractKnowns(string $frag): array
    {
        if (!$this->variantPattern || !$this->variantMap) return [];

        $found = [];
        if (preg_match_all($this->variantPattern, mb_strtolower($frag), $m)) {
            foreach ($m[0] as $hit) {
                $canon = $this->variantMap[$hit] ?? null;
                if (!$canon) continue;
                $found[] = $canon;
            }
        }
        return $found;
    }

    private function ensureIndex(): void
    {
        if ($this->variantMap !== null) return;

        $map = [];

        // include both canonicals and variants
        foreach ($this->synonyms as $canon => $vars) {
            $lcCanon = mb_strtolower($canon);
            $map[$lcCanon] = $canon;
            foreach ($vars as $v) {
                $map[mb_strtolower($v)] = $canon;
            }
        }

        // Build a single alternation regex of all variants, longest first
        $variants = array_keys($map);
        usort($variants, fn($a,$b)=>mb_strlen($b)<=>mb_strlen($a));
        $alts = array_map(
            fn($v)=>preg_quote($v, '/'),
            $variants
        );
        $this->variantMap = $map;
        $this->variantPattern = '/\b(?:' . implode('|', $alts) . ')\b/u';
    }

    private function canonicalize(string $s): string
    {
        $raw = mb_strtolower(trim($s));
        if ($raw === '') return '';

        // exact title match first
        $title = $this->title($s);
        foreach (array_keys($this->synonyms) as $canon) {
            if ($title === $canon) return $canon;
        }

        // exact variant match
        foreach ($this->synonyms as $canon => $variants) {
            foreach ($variants as $v) {
                if ($raw === mb_strtolower($v)) return $canon;
            }
        }

        // light fuzzy on both variants and canonicals
        $best = '';
        $bestDist = 3; // keep tight; multi-hit extractor handles most cases
        foreach ($this->synonyms as $canon => $variants) {
            foreach ($variants as $v) {
                $d = levenshtein($raw, mb_strtolower($v));
                if ($d < $bestDist) { $bestDist = $d; $best = $canon; }
            }
            $d2 = levenshtein($raw, mb_strtolower($canon));
            if ($d2 < $bestDist) { $bestDist = $d2; $best = $canon; }
        }

        // If the fragment contains a known single-word variant inside,
        // treat that as a hit (e.g., "ranch trusting" → "ranch").
        if ($best === '' && $this->variantPattern && $this->variantMap) {
            if (preg_match($this->variantPattern, $raw, $m)) {
                $hit = $m[0];
                $best = $this->variantMap[$hit] ?? '';
            }
        }

        return $best !== '' ? $best : $title;
    }

    private function title(string $s): string
    {
        return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8');
    }
    public function filterByCategory(array $mods, ?string $category): array
    {
        // Minimal, safe behavior: canonicalize & dedupe the provided list.
        // Category filtering can be layered in later if needed.
        return $this->resolveList($mods);
    }
}
