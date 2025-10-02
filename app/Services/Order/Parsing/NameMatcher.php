<?php
namespace App\Services\Order\Parsing;


use App\Services\Order\Contracts\MenuRepository;
use App\Services\Order\Contracts\TextNormalizer;


final class NameMatcher
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly TextNormalizer $N,
    ) {
    }


    public function findMenuIdByName(string $spokenName, ?string $size): ?int
    {
        $spoken = $this->N->normName($spokenName);
        if ($spoken === '') {
            return null;
        }
        $menu = $this->menu->menu();


        if ($size !== null) {
            foreach ($menu as $idx => $m) {
                $id = (int)($m['id'] ?? $idx);
                if ($this->N->normName($m['name'] ?? '') === $spoken && $this->sizeMatches($m['size'] ?? null, $size)) {
                    return $id;
                }
            }
        }


        $cands = [];
        foreach ($menu as $idx => $m) {
            if ($this->N->normName($m['name'] ?? '') === $spoken) {
                $cands[] = $m + ['id' => (int)($m['id'] ?? $idx)];
            }
        }
        if ($cands) {
            $picked = $this->pickBySize($cands, $size);
            return (int)$picked['id'];
        }


        $spokenTokens = array_filter(explode(' ', $spoken));
        $best = null;
        $bestScore = -1;
        foreach ($menu as $idx => $m) {
            $nm = $this->N->normName($m['name'] ?? '');
            $menuTokens = array_filter(explode(' ', $nm));
            $hit = 0;
            foreach ($spokenTokens as $t) {
                if (in_array($t, $menuTokens, true)) {
                    $hit++;
                }
            }
            if ($hit > 0 && $this->sizeMatches($m['size'] ?? null, $size)) {
                $score = $hit * 10 + (int)str_starts_with($nm, $spoken);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $m + ['id' => (int)($m['id'] ?? $idx)];
                }
            }
        }
        if ($best) {
            return (int)$best['id'];
        }


        $bestId = null;
        $bestDist = PHP_INT_MAX;
        foreach ($menu as $idx => $m) {
            if ($size !== null && !$this->sizeMatches($m['size'] ?? null, $size)) {
                continue;
            }
            $d = levenshtein($spoken, $this->N->normName($m['name'] ?? ''));
            if ($d < $bestDist) {
                $bestDist = $d;
                $bestId = (int)($m['id'] ?? $idx);
            }
        }
        return ($bestId !== null && $bestDist <= 3) ? $bestId : null;
    }


    private function sizeMatches(?string $menuSize, ?string $want): bool
    {
        return $want === null || $this->normalizeSize($menuSize) === $want;
    }


    private function normalizeSize(?string $s): ?string
    {
        if (!$s) {
            return null;
        }
        $x = mb_strtolower(trim($s));
        return match ($x) {
            'small' => 'Small',
            'regular' => 'Regular',
            'large' => 'Large',
            default => null
        };
    }


    private function pickBySize(array $cands, ?string $want): array
    {
        if ($want) {
            foreach ($cands as $c) {
                if ($this->normalizeSize($c['size'] ?? null) === $want) {
                    return $c;
                }
            }
        }
        foreach ($cands as $c) {
            if ($this->normalizeSize($c['size'] ?? null) === 'Regular') {
                return $c;
            }
        }
        foreach ($cands as $c) {
            if ($this->normalizeSize($c['size'] ?? null) === 'Large') {
                return $c;
            }
        }
        return $cands[0];
    }
}
