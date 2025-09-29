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

        // Clear order
        if ($this->isClear($norm)) {
            return ['action' => 'clear', 'items' => $this->clear()];
        }

        // ADD by <size> + <name>
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?:a|an)\s+)?' .
            '(?<size>small|regular|large)\s+' .          // ← REQUIRED size here
            '(?<name>.+?)(?=\s+(?:with|without)\b|$)' .  // name until with/without or end
            '(?:\s+\bwith\b\s+(?<with>.*?))?' .
            '(?:\s+\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm,
            $m
        )) {
            $qty     = 1;
            $size    = $this->normalizeSize($m['size'] ?? null);
            $name    = trim($m['name'] ?? '');
            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');

            $ok = $this->addByName($name, $qty, $adds, $removes, $size);
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }

        // ADD: qty? + (number|no.|#)? + id + optional with/without
        // Examples: "add number 3", "add #3 with ketchup", "add two number 5 without onions"
        // Name-based add (qty? size? name (with ...)? (without ...)?)
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?:at|in|on|to|for|please|me|us|the)\s+)*' .        // <-- tolerate fillers after verb
            '(?:(?<qty>\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s+)?' .
            '(?:a|an)?\s*(?:number|no\.|#)?\s*(?<id>\d+)\b' .
            '(?:.*?\bwith\b\s+(?<with>.*?))?' .
            '(?:.*?\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm,
            $m
        )) {
            $qty     = $this->toQty($m['qty'] ?? '') ?: 1;
            $id      = (int)($m['id'] ?? 0);
            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');

            $ok = $this->addByMenuId($id, $qty, $adds, $removes);
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }

// Fallback: name add with optional qty and optional size
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?<qty>\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s+)?' . // qty?  <- NEW
            '(?:of\s+)?' .                                                   // "add six of onion rings" support
            '(?:(?:a|an)\s+)?' .
            '(?:(?<size>small|regular|large)\s+)?' .                         // size? <- NEW
            '(?<name>.+?)(?=\s+(?:with|without)\b|$)' .                      // name until with/without or end
            '(?:\s+\bwith\b\s+(?<with>.*?))?' .
            '(?:\s+\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm,
            $m
        )) {
            $qty     = $this->toQty($m['qty'] ?? '') ?: 1;
            $size    = $this->normalizeSize($m['size'] ?? null);
            $name    = trim($m['name'] ?? '');
            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');

            $ok = $this->addByName($name, $qty, $adds, $removes, $size);
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }

        // REMOVE examples: "remove number 3", "remove #1", "remove lemonade"
        if (preg_match(
            '/^(?:remove|delete|drop|minus)\s+' .
            '(?:a|an)?\s*(?:number|no\.|#)?\s*(?<id>\d+)\b' .
            '$/siu',
            $norm,
            $m
        )) {
            $id = (int)($m['id'] ?? 0);
            $ok = $this->decrementById($id, 1);
            return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
        }
        if (preg_match('/^(?:remove|delete|drop|minus)\s+(?<name>.+)$/siu', $norm, $m)) {
            $ok = $this->decrementByName(trim($m['name']), 1);
            return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
        }

        return ['action' => 'noop', 'items' => $this->all()];
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
        // BBQ variants
        $s = preg_replace('/\bbarbe?cue\b/iu', 'bbq', $s) ?? $s;           // “barbecue/barbeque”
        $s = preg_replace('/\bbar[-\s]*b[-\s]*q\b/iu', 'bbq', $s) ?? $s;   // “bar-b-q”, “bar b q”
        $s = preg_replace('/\bb\.?\s*b\.?\s*q\.?\b/iu', 'bbq', $s) ?? $s;  // “b.b.q”, “b b q”

        // Milkshake
        $s = preg_replace('/\bmilk[-\s]*shake\b/iu', 'milkshake', $s) ?? $s;

        // Mac & Cheese
        $s = preg_replace('/\bmac\s*(?:and|&|n[\'’]?)\s*cheese\b/iu', 'mac & cheese', $s) ?? $s;

        // Jalapeño normalize (spoken often as 'jalapeno')
        $s = preg_replace('/\bjalapen(?:o|os)\b/iu', 'jalapeno', $s) ?? $s;

        // Coke → Coca-Cola (optional)
        $s = preg_replace('/\bcoke\b/iu', 'coca cola', $s) ?? $s;

        return $s;
    }
    private function normalize(string $s): string
    {
        $s = trim($s ?? '');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = preg_replace('/[.!?]+$/u', '', $s) ?? $s; // strip trailing punc

        // "and at ..." -> "add ..."
        $s = preg_replace('/^\s*(?:and\s+at)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;

        // starters -> "add ..."
        $s = preg_replace('/^\s*(?:and|also|plus|yeah|yep|ok|okay|uh|um|please|then|at)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;

        // NEW: "i had ..." / "i have ..." misheard for "add ..."
        $s = preg_replace('/^\s*(?:i\s+had|i\s+have|i\'d|i\s+add)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;

        // NEW: "i had to ..." -> "add two ..."
        $s = preg_replace('/^\s*i\s*had\s+to\b/iu', 'add two', $s) ?? $s;

        // Existing: remove fillers right after add (add in/to/for …)
        $s = preg_replace('/^\s*add\s+(?:(?:in|on|to|for|please|me|us|the)\s+)+/iu', 'add ', $s) ?? $s;

        // NEW: "add to ..." / "add too ..." -> "add two ..."
        $s = preg_replace('/^add\s+(?:to|too)\b/iu', 'add two', $s) ?? $s;

        // Convert "number two"/"no. two"/"#two" -> "number 2" (and allow homophones)
        $s = preg_replace_callback(
            '/\b(?:number|no\.|#)\s*(one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\b/iu',
            fn($m) => 'number ' . ($this->toQty($m[1]) ?: 0),
            $s
        ) ?? $s;

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
            'two'=>2, 'to'=>2, 'too'=>2,              // ← NEW
            'three'=>3,
            'four'=>4, 'for'=>4,                      // ← NEW (ASR “for” → 4)
            'five'=>5,
            'six'=>6,
            'seven'=>7,
            'eight'=>8,
            'nine'=>9,
            'ten'=>10,
            'eleven'=>11,
            'twelve'=>12,
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
