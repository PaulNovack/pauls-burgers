<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

class OrderService
{
    public function __construct(
        private readonly Session $session,
        private readonly string $key = 'user.order.items'
    ) {}

    /** Public API: return all lines from session */
    public function all(): array
    {
        return $this->session->get($this->key, []);
    }

    public function clear(): array
    {
        $this->session->put($this->key, []);
        return [];
    }

    /** Main entry: parse a natural command and mutate order in session */
    public function processCommand(string $text): array
    {
        $norm = $this->normalize($text);

        // CLEAR
        if ($this->isClear($norm)) {
            return ['action' => 'clear', 'items' => $this->clear()];
        }

        // ---------- ADD: explicit <size> + <name> ----------
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?:a|an)\s+)?' .
            '(?<size>small|regular|large)\s+' .
            '(?<name>.+?)(?=\s+(?:with|without)\b|$)' .
            '(?:\s+\bwith\b\s+(?<with>.*?))?' .
            '(?:\s+\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm, $m
        )) {
            $qty     = 1;
            $size    = $this->normalizeSize($m['size'] ?? null);
            $name    = trim($m['name'] ?? '');
            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');
            $ok = $this->addByName($name, $qty, $adds, $removes, $size);
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }

// qty? + [of]? + (number|no.|#)? + id (digits OR number-words; allow plural/'s)
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?:at|in|on|to|for|please|me|us|the)\s+)*' .
            '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s*,?\s+)?' .
            '(?:of\s+)?' .
            '(?:a|an)?\s*(?:number|no\.|#)?\s*' .
            '(?:(?<id>\d+)\s*(?:\'s|’s|s)?|(?<idw>(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty)(?:[-\s]+(?:one|two|three|four|five|six|seven|eight|nine))?)(?:\'s|’s|s|es|ies)?)\b' .
            '(?:.*?\bwith\b\s+(?<with>.*?))?' .
            '(?:.*?\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm,
            $m
        )) {
            $qty  = $this->toQty($m['qty'] ?? '') ?: 1;
            $id   = !empty($m['id'])
                ? (int)$m['id']
                : $this->wordsToNumber($this->normalizeNumberWord($m['idw'] ?? ''));

            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');

            $ok = $id > 0 ? $this->addByMenuId($id, $qty, $adds, $removes) : false;
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }



        // ---------- ADD: fallback name with optional qty + optional size ----------
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s+)?' .
            '(?:of\s+)?' .
            '(?:(?:a|an)\s+)?' .
            '(?:(?<size>small|regular|large)\s+)?' .
            '(?<name>.+?)(?=\s+(?:with|without)\b|$)' .
            '(?:\s+\bwith\b\s+(?<with>.*?))?' .
            '(?:\s+\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm, $m
        )) {
            $qty     = $this->toQty($m['qty'] ?? '') ?: 1;
            $size    = $this->normalizeSize($m['size'] ?? null);
            $name    = trim($m['name'] ?? '');
            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');
            $ok = $this->addByName($name, $qty, $adds, $removes, $size);
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }

        // ---------- REMOVE by id ----------
        if (preg_match(
            '/^(?:remove|delete|drop|minus)\s+' .
            '(?:a|an)?\s*(?:number|no\.|#)?\s*(?<id>\d+)\b' .
            '$/siu', $norm, $m
        )) {
            $id = (int)($m['id'] ?? 0);
            $ok = $this->decrementById($id, 1);
            return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
        }

        // ---------- REMOVE by name ----------
        if (preg_match('/^(?:remove|delete|drop|minus)\s+(?<name>.+)$/siu', $norm, $m)) {
            $ok = $this->decrementByName(trim($m['name']), 1);
            return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
        }

        return ['action' => 'noop', 'items' => $this->all()];
    }

    /** Decrement by id, optionally filtering by size/add/remove. Returns true if anything changed. */
    private function decrementByIdWithMods(int $id, int $qty, ?string $size, array $needAdd, array $needRemove): bool
    {
        $items = $this->all();
        if (empty($items) || $qty <= 0) return false;

        // Collect candidate line indexes
        $candidates = [];
        foreach ($items as $k => $line) {
            if ((int)($line['id'] ?? 0) !== $id) continue;

            // size match (if requested)
            if ($size !== null && $this->normalizeSize($line['size'] ?? null) !== $size) continue;

            // modifiers must be subsets (case-insensitive, plural-safe)
            if ($needAdd && !$this->listIsSubset($needAdd, $line['add'] ?? [])) continue;
            if ($needRemove && !$this->listIsSubset($needRemove, $line['remove'] ?? [])) continue;

            $candidates[] = $k;
        }
        if (!$candidates) return false;

        // Prefer more-specific matches (more modifiers) first
        usort($candidates, function ($a, $b) use ($items) {
            $sa = count($items[$a]['add'] ?? []) + count($items[$a]['remove'] ?? []);
            $sb = count($items[$b]['add'] ?? []) + count($items[$b]['remove'] ?? []);
            return $sb <=> $sa;
        });

        // Decrement across candidates until qty is satisfied
        $remaining = max(1, $qty);
        foreach ($candidates as $k) {
            $cur = max(1, (int)($items[$k]['quantity'] ?? 1));
            $take = min($cur, $remaining);
            $new = $cur - $take;
            if ($new <= 0) { unset($items[$k]); } else { $items[$k]['quantity'] = $new; }
            $remaining -= $take;
            if ($remaining <= 0) break;
        }

        // Reindex and persist
        $this->session->put($this->key, array_values($items));
        return true;
    }

    /** Case-insensitive, plural/possessive tolerant subset check. */
    private function listIsSubset(array $needles, array $haystack): bool
    {
        if (!$needles) return true;
        $hs = [];
        foreach ($haystack as $h) $hs[$this->normMod($h)] = true;
        foreach ($needles as $n) {
            $k = $this->normMod($n);
            if ($k === '') continue;
            if (!isset($hs[$k])) return false;
        }
        return true;
    }

    /** Normalize modifier token: lowercase, strip punctuation, singularize simple plurals. */
    private function normMod(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N} ]+/u', '', $s) ?? '';
        // simple singularization
        if (preg_match('/(.*[^aeiou])ies$/u', $s, $m)) return $m[1].'y';   // onions -> onion, pickles -> pickle, etc.
        if (preg_match('/(.*)s$/u', $s, $m)) return $m[1];                 // rings -> ring
        return $s;
    }

    private function normalizeNumberWord(string $w): string
    {
        $w = mb_strtolower(trim($w));
        if (preg_match('/^(.*?)(?:\'s|’s|s|es)$/u', $w, $m)) {
            $w = $m[1]; // strip plural/possessive
        }
        if ($w === 'too' || $w === 'to') $w = 'two';
        if ($w === 'for') $w = 'four';
        return $w;
    }







    /** ---------- Internals ---------- */
    private function normalizeSize(?string $size): ?string
    {
        if (!$size) return null;
        $s = mb_strtolower(trim($size));
        return match ($s) {
            'small'   => 'Small',   // in case you add it later
            'regular' => 'Regular',
            'large'   => 'Large',
            default   => null,
        };
    }

    private function stripDiacritics(string $s): string
    {
        // enough for our menu (jalapeño -> jalapeno, etc.)
        $from = ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ','’','ʼ','ʻ','ˈ'];
        $to   = ['a','e','i','o','u','n','a','e','i','o','u','n',"'", "'", "'", "'"];
        return str_replace($from, $to, $s);
    }

    /** Map common spoken variants to menu spellings */
    private function lexify(string $s): string
    {
        // BBQ variants...
        $s = preg_replace('/\bbarbe?cue\b/iu', 'bbq', $s) ?? $s;
        $s = preg_replace('/\bbar[-\s]*b[-\s]*q\b/iu', 'bbq', $s) ?? $s;
        $s = preg_replace('/\bb\.?\s*b\.?\s*q\.?\b/iu', 'bbq', $s) ?? $s;

        // Milkshake
        $s = preg_replace('/\bmilk[-\s]*shake\b/iu', 'milkshake', $s) ?? $s;

        // Mac & Cheese
        $s = preg_replace('/\bmac\s*(?:and|&|n[\'’]?)\s*cheese\b/iu', 'mac & cheese', $s) ?? $s;

        // Jalapeño normalize
        $s = preg_replace('/\bjalapen(?:o|os)\b/iu', 'jalapeno', $s) ?? $s;

        // Coke → Coca-Cola
        $s = preg_replace('/\bcoke\b/iu', 'coca cola', $s) ?? $s;

        // >>> NEW: Treat "cheeseburger" like "cheese burger" (helps "blue cheese burger" vs "cheeseburger")
        $s = preg_replace('/\bcheeseburgers\b/iu', 'cheese burgers', $s) ?? $s;
        $s = preg_replace('/\bcheeseburger\b/iu', 'cheese burger', $s) ?? $s;

        return $s;
    }

    private function normalize(string $s): string
    {
        $s = trim($s ?? '');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        // strip trailing punctuation
        $s = preg_replace('/[.!?]+$/u', '', $s) ?? $s;

        // starters → "add "
        $s = preg_replace('/^\s*(?:and\s+at)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;
        $s = preg_replace('/^\s*(?:and|also|plus|yeah|yep|ok|okay|uh|um|please|then|at)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;

        // map common request forms to "add "
        $s = preg_replace('/^\s*(?:i\s+want|give\s+me|could\s+i\s+have|can\s+i\s+have|may\s+i\s+have|let\s+me\s+(?:get|have)|get\s+me|i\'ll\s+(?:take|have)|i\s+will\s+(?:take|have)|i\s+would\s+like|i\'d\s+like)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;

        // mishears: "i had ..." / "i have ..." → add
        $s = preg_replace('/^\s*(?:i\s+had|i\s+have|i\'d|i\s+add)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;

        // handle "add to/too ..." → "add two ..." BEFORE stripping fillers after "add"
        $s = preg_replace('/^\s*add\s+(?:to|too)\b/iu', 'add two', $s) ?? $s;

        // after "add", drop filler preps (keep the rule above!)
        $s = preg_replace('/^\s*add\s+(?:(?:in|on|for|please|me|us|the)\s+)+/iu', 'add ', $s) ?? $s;

        // Convert "number two/too/to" etc. → digits (so ID-matcher sees digits)
        $s = preg_replace_callback(
            '/\b(?:number|no\.|#)\s*(one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\b/iu',
            fn($m) => 'number ' . ($this->toQty($m[1]) ?: 0),
            $s
        ) ?? $s;

        // Normalize possessive/plural numeric IDs: "number 16's"/"no. 16s" → "number 16"
        $s = preg_replace('/\b(?:number|no\.|#)\s*(\d+)\s*(?:\'s|’s|s)\b/iu', 'number $1', $s) ?? $s;

        return $s;
    }







    private function isClear(string $s): bool
    {
        $lc = mb_strtolower(trim($s));
        return
            preg_match('/^\s*(clear|reset)\s*(list|order)?\s*[.?]?\s*$/u', $lc) ||
            preg_match('/^\s*(delete|wipe|erase)\s+(list|order)\s*[.?]?\s*$/u', $lc) ||
            preg_match('/^\s*(new|create new|start new)\s+(list|order)\s*[.?]?\s*$/u', $lc);
    }

    private function toQty(?string $s): int
    {
        if (!$s) return 0;
        $s = mb_strtolower(trim($s));
        if (ctype_digit($s)) return max(1, (int)$s);
        $map = [
            'one'=>1,
            'two'=>2, 'to'=>2, 'too'=>2,
            'three'=>3,
            'four'=>4, 'for'=>4,
            'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,'ten'=>10,'eleven'=>11,'twelve'=>12,
        ];
        return $map[$s] ?? 0;
    }



    private function splitList(string $s): array
    {
        $s = trim($s);
        if ($s === '') return [];
        // split on commas or “and”/&
        $parts = preg_split('/\s*(?:,|and|&)\s*/iu', $s) ?: [];
        $parts = array_map(fn($p) => $this->title(trim($p)), $parts);
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }

    /** Session structure: array of lines keyed by a unique key (id+size+add/remove set) */
    private function addByMenuId(int $id, int $qty, array $add, array $remove): bool
    {
        $menu = $this->menu();
        $menuItem = $menu[$id] ?? null;
        if (!$menuItem) return false;

        $items = $this->all();
        $key = $this->lineKey($id, $menuItem['size'] ?? null, $add, $remove);

        if (!isset($items[$key])) {
            $items[$key] = $this->makeLine($menuItem, $qty, $add, $remove);
        } else {
            $items[$key]['quantity'] = max(1, (int)$items[$key]['quantity'] + max(1, $qty));
            // de-dup adds/removes
            $items[$key]['add'] = $this->uniqueList(array_merge($items[$key]['add'] ?? [], $add));
            $items[$key]['remove'] = $this->uniqueList(array_merge($items[$key]['remove'] ?? [], $remove));
        }
        $this->session->put($this->key, $items);
        return true;
    }

    /** Case/space tolerant, size-aware lookup by name with simple fuzzy fallback */
    private function addByName(string $spokenName, int $qty, array $add, array $remove, ?string $size = null): bool
    {
        $spoken = $this->normName($spokenName);
        if ($spoken === '') return false;

        $menu = $this->menu();

        // 1) Exact normalized name + size (ONLY if size was specified)
        if ($size !== null) {
            foreach ($menu as $idx => $m) {
                $itemId = (int)($m['id'] ?? $idx);
                if ($this->normName($m['name'] ?? '') === $spoken && $this->sizeMatches($m['size'] ?? null, $size)) {
                    return $this->addByMenuId($itemId, $qty, $add, $remove);
                }
            }
        }

        // 2) Exact name ignoring size -> pick best size (Regular > Large)
        $cands = [];
        foreach ($menu as $idx => $m) {
            if ($this->normName($m['name'] ?? '') === $spoken) {
                $cands[] = $m + ['id' => (int)($m['id'] ?? $idx)];
            }
        }
        if ($cands) {
            $picked = $this->pickBySize($cands, $size); // null -> prefers Regular
            return $this->addByMenuId((int)$picked['id'], $qty, $add, $remove);
        }

        // 3) Token-subset fallback (e.g., "curly fries" matches "Curly Fries")
        $spokenTokens = array_filter(explode(' ', $spoken));
        $best = null;
        $bestScore = -1;
        foreach ($menu as $idx => $m) {
            $nm = $this->normName($m['name'] ?? '');
            $menuTokens = array_filter(explode(' ', $nm));

            // score = # of spoken tokens present in menu name
            $hit = 0;
            foreach ($spokenTokens as $t) {
                if (in_array($t, $menuTokens, true)) $hit++;
            }
            if ($hit > 0 && $this->sizeMatches($m['size'] ?? null, $size)) {
                $score = $hit * 10 + (int)str_starts_with($nm, $spoken); // prefer starts-with ties
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $m + ['id' => (int)($m['id'] ?? $idx)];
                }
            }
        }
        if ($best) {
            return $this->addByMenuId((int)$best['id'], $qty, $add, $remove);
        }

        // 4) Small-typo Levenshtein fallback (name-only)
        $bestId = null; $bestDist = PHP_INT_MAX;
        foreach ($menu as $idx => $m) {
            $d = levenshtein($spoken, $this->normName($m['name'] ?? ''));
            if ($d < $bestDist) { $bestDist = $d; $bestId = (int)($m['id'] ?? $idx); }
        }
        if ($bestId !== null && $bestDist <= 3) {
            return $this->addByMenuId($bestId, $qty, $add, $remove);
        }

        return false;
    }


    private function pickBySize(array $candidates, ?string $want): array
    {
        // If user specified, pick that; else prefer Regular, then Large, else the first.
        if ($want) {
            foreach ($candidates as $c) if ($this->sizeMatches($c['size'] ?? null, $want)) return $c;
        }
        foreach ($candidates as $c) if ($this->normalizeSize($c['size'] ?? null) === 'Regular') return $c;
        foreach ($candidates as $c) if ($this->normalizeSize($c['size'] ?? null) === 'Large')   return $c;
        return $candidates[0];
    }
    private function sizeMatches(?string $menuSize, ?string $want): bool
    {
        if ($want === null) return true;          // if user didn’t specify, any size is acceptable
        return $this->normalizeSize($menuSize) === $want;
    }

    /** Use this for BOTH spoken and menu names before comparing */
    private function normName(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = $this->stripDiacritics($s);
        $s = $this->lexify($s);
        $s = str_replace('-', ' ', $s);                              // unify hyphens
        $s = preg_replace('/[^\p{L}\p{N}& ]+/u', ' ', $s) ?? $s;     // drop punctuation except &
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;                  // collapse spaces
        return $s;
    }
    private function decrementById(int $id, int $qty): bool
    {
        $items = $this->all();
        $changed = false;
        foreach ($items as $k => $line) {
            if ((int)$line['id'] === $id) {
                $newQty = max(0, ((int)($line['quantity'] ?? 1)) - max(1, $qty));
                if ($newQty === 0) unset($items[$k]); else $items[$k]['quantity'] = $newQty;
                $changed = true;
            }
        }
        if ($changed) $this->session->put($this->key, $items);
        return $changed;
    }

    private function decrementByName(string $name, int $qty): bool
    {
        $items = $this->all();
        $changed = false;
        foreach ($items as $k => $line) {
            if (mb_strtolower($line['name'] ?? '') === mb_strtolower($name)) {
                $newQty = max(0, ((int)($line['quantity'] ?? 1)) - max(1, $qty));
                if ($newQty === 0) unset($items[$k]); else $items[$k]['quantity'] = $newQty;
                $changed = true;
            }
        }
        if ($changed) $this->session->put($this->key, $items);
        return $changed;
    }

    private function makeLine(array $menuItem, int $qty, array $add, array $remove): array
    {
        return [
            'id'       => (int)$menuItem['id'],
            'name'     => (string)$menuItem['name'],
            'price'    => (float)$menuItem['price'],
            'type'     => (string)$menuItem['type'],
            'category' => $menuItem['category'] ?? null,
            'size'     => $menuItem['size'] ?? null,
            'toppings' => $menuItem['toppings'] ?? null,
            'quantity' => max(1, $qty),
            'add'      => $this->uniqueList($add),
            'remove'   => $this->uniqueList($remove),
        ];
    }

    private function uniqueList(array $xs): array
    {
        $seen = [];
        $out = [];
        foreach ($xs as $x) {
            $k = mb_strtolower(trim($x));
            if ($k === '') continue;
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = $this->title($x);
        }
        return $out;
    }

    private function lineKey(int $id, ?string $size, array $add, array $remove): string
    {
        sort($add, SORT_NATURAL | SORT_FLAG_CASE);
        sort($remove, SORT_NATURAL | SORT_FLAG_CASE);
        return $id . '|' . ($size ?? 'none') . '|' . implode(',', $add) . '|' . implode(',', $remove);
        // This guarantees “same id + same size + same modifiers” is merged.
    }

    private function title(string $s): string
    {
        return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8');
    }

    /** Load menu items keyed by id for fast lookup. Expects config/menu.php -> ['items'=>[...]] */
    private function menu(): array
    {
        $items = config('menu.items', []);
        $out = [];
        foreach ($items as $m) {
            // ensure id is the key
            $out[(int)$m['id']] = $m + ['id' => (int)$m['id']];
        }
        return $out;
    }
}
