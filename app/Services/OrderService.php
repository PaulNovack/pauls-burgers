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
        // 1) Normalize raw ASR text
        $norm = $this->normalize($text);

        // 2) Clear order
        if ($this->isClear($norm)) {
            return ['action' => 'clear', 'items' => $this->clear()];
        }

        // 3) ADD by (qty?) + [of]? + (number|no.|#)? + id (digits or number-words) + optional with/without
        //    Examples: "add number 3", "add two number 5 without onions", "add 6, #16s with ketchup"
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?:at|in|on|to|for|please|me|us|the)\s+)*' .
            '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s*,?\s+)?' . // qty?, comma ok
            '(?:of\s+)?' .
            '(?:a|an)?\s*(?:number|no\.|#)?\s*' .
            '(?:(?<id>\d+)\s*(?:\'s|’s|s)?|(?<idw>(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty)(?:[-\s]+(?:one|two|three|four|five|six|seven|eight|nine))?)(?:\'s|’s|s|es|ies)?)\b' .
            '(?:\s+(?<size>small|regular|large))?' .                                          // optional trailing size
            '(?:.*?\bwith\b\s+(?<with>.*?))?' .
            '(?:.*?\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm,
            $m
        )) {
            $qty   = $this->toQty($m['qty'] ?? '') ?: 1;
            $size  = $this->normalizeSize($m['size'] ?? null);

            if (!empty($m['id'])) {
                $id = (int)$m['id'];
            } else {
                $phrase = $this->normalizeNumberWord($m['idw'] ?? '');
                $id     = $this->wordsToNumber($phrase); // 0..99+
            }

            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');

            // ID wins; if size was spoken, the chosen line's size comes from the menu id anyway
            $ok = $id > 0 ? $this->addByMenuId($id, $qty, $adds, $removes) : false;
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }

        // 4) ADD by qty? + [size]? + name (+ with/without)
        //    Examples: "add six onion rings", "add a large lemonade", "add veggie burger without onion"
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?:(?:at|in|on|to|for|please|me|us|the)\s+)*' .
            '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s+)?' . // optional qty
            '(?:(?<size>small|regular|large)\s+)?' .                                                          // optional size
            '(?<name>.+?)(?=\s+(?:with|without)\b|$)' .                                                      // name until with/without or end
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

        // 5) Fallback ADD by name only (+ with/without) — qty defaults to 1
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+' .
            '(?<name>.+?)' .
            '(?:\s+\bwith\b\s+(?<with>.*?))?' .
            '(?:\s+\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm,
            $m
        )) {
            $name    = trim($m['name'] ?? '');
            $adds    = $this->splitList($m['with'] ?? '');
            $removes = $this->splitList($m['without'] ?? '');
            $ok = $this->addByName($name, 1, $adds, $removes, null);
            return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
        }

        // 6) REMOVE by (qty?) + [of]? + (number|no.|#)? + id (digits or number-words) + optional size/with/without
        //    Example: "remove two number twos without onions", "drop #17 large with ketchup"
        if (preg_match(
            '/^(?:remove|delete|drop|minus|take\s+off)\s+' .
            '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s*,?\s+)?' .
            '(?:of\s+)?' .
            '(?:a|an)?\s*(?:number|no\.|#)?\s*' .
            '(?:(?<id>\d+)\s*(?:\'s|’s|s)?|(?<idw>(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty)(?:[-\s]+(?:one|two|three|four|five|six|seven|eight|nine))?)(?:\'s|’s|s|es|ies)?)\b' .
            '(?:\s+(?<size>small|regular|large))?' .
            '(?:.*?\bwith\b\s+(?<with>.*?))?' .
            '(?:.*?\bwithout\b\s+(?<without>.*))?' .
            '$/siu',
            $norm,
            $m
        )) {
            $qty   = $this->toQty($m['qty'] ?? '') ?: 1;
            $size  = $this->normalizeSize($m['size'] ?? null);

            if (!empty($m['id'])) {
                $id = (int)$m['id'];
            } else {
                $phrase = $this->normalizeNumberWord($m['idw'] ?? '');
                $id     = $this->wordsToNumber($phrase);
            }

            $needAdd    = $this->splitList($m['with'] ?? '');
            $needRemove = $this->splitList($m['without'] ?? '');

            $ok = $id > 0 ? $this->decrementByIdWithMods($id, $qty, $size, $needAdd, $needRemove) : false;
            return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
        }

        // 7) Simple REMOVE by name (fallback), qty defaults to 1, no size/mod filters
        if (preg_match('/^(?:remove|delete|drop|minus)\s+(?<name>.+)$/siu', $norm, $m)) {
            $ok = $this->decrementByName(trim($m['name']), 1);
            return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
        }

        // 8) No recognized action
        return ['action' => 'noop', 'items' => $this->all()];
    }

    /** Normalize number-word phrases and convert to an integer (supports 0..999+). */
    private function wordsToNumber(string $s): int
    {
        $s = trim($s);
        if ($s === '') return 0;
        $s = mb_strtolower($s);

        // Accept common ASR homophones
        $units = [
            'zero'=>0,'one'=>1,'two'=>2,'to'=>2,'too'=>2,'three'=>3,'four'=>4,'for'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,
            'ten'=>10,'eleven'=>11,'twelve'=>12,'thirteen'=>13,'fourteen'=>14,'fifteen'=>15,'sixteen'=>16,'seventeen'=>17,'eighteen'=>18,'nineteen'=>19,
        ];
        $tens = ['twenty'=>20,'thirty'=>30,'forty'=>40,'fifty'=>50,'sixty'=>60,'seventy'=>70,'eighty'=>80,'ninety'=>90];

        $total = 0; $current = 0;
        $tokens = preg_split('/\s+/u', $s) ?: [];
        foreach ($tokens as $w) {
            if (isset($units[$w])) { $current += $units[$w]; continue; }
            if (isset($tens[$w]))  { $current += $tens[$w];  continue; }
            if ($w === 'hundred')  { if ($current === 0) $current = 1; $current *= 100; continue; }
            if ($w === 'thousand') { if ($current === 0) $current = 1; $total += $current * 1000; $current = 0; continue; }
            // unknown token → ignore
        }
        return $total + $current;
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

    /** Pre-normalize number-word capture like "sixteen's", "threes", "thirty-one" → "thirty one". */
    private function normalizeNumberWord(string $s): string
    {
        $s = mb_strtolower(trim($s));
        // unify hyphens/dashes to spaces
        $s = str_replace(['–','—','-'], ' ', $s);
        // keep only letters/numbers/spaces
        $s = preg_replace('/[^\p{L}\p{N} ]+/u', '', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';

        // common ASR homophones for qty/ids
        $s = preg_replace('/\bto\b/u',  'two',  $s) ?? $s;
        $s = preg_replace('/\btoo\b/u', 'two',  $s) ?? $s;
        $s = preg_replace('/\bfor\b/u', 'four', $s) ?? $s;

        // singularize the LAST token only: "threes"->"three", "sixties"->"sixty", "sixteen's"->"sixteen"
        if ($s !== '') {
            $tokens = preg_split('/\s+/u', $s) ?: [];
            if ($tokens) {
                $i = count($tokens)-1;
                $last = $tokens[$i];
                // strip possessive first
                $last = preg_replace('/\'s$/u', '', $last) ?? $last;
                // ies -> y
                if (preg_match('/(.*[^aeiou])ies$/u', $last, $m)) {
                    $last = $m[1].'y';
                } elseif (preg_match('/(.*)s$/u', $last, $m)) {
                    $last = $m[1];
                }
                $tokens[$i] = $last;
                $s = implode(' ', $tokens);
            }
        }
        return $s;
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

        // "and at ..." -> "add ..."
        $s = preg_replace('/^\s*(?:and\s+at)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;

        // Map polite/variant verbs to "add ..."
        $s = preg_replace(
            '/^\s*(?:and|also|plus|yeah|yep|ok|okay|uh|um|please|then|at|'
            .'i\s+want|i\'?d\s+like|i\s+would\s+like|i\'?ll\s+have|give\s+me|gimme|include|'
            .'could\s+i\s+have|can\s+i\s+get|may\s+i\s+have|i\s+need)\b[,:-]?\s*/iu',
            'add ',
            $s
        ) ?? $s;

        // Remove filler words right after "add"
        $s = preg_replace('/^\s*add\s+(?:(?:in|on|to|for|please|me|us|the)\s+)+/iu', 'add ', $s) ?? $s;

        // ASR quirk: "add to X" / "add too X" -> "add two X"
        $s = preg_replace('/^\s*add\s+(?:to|too)\b/iu', 'add two ', $s) ?? $s;

        // --------- BIG ONE: convert "number <word(s)>" -> "number <digits>" ----------
        // Handles "number two", "number thirty", "number thirty-one", "number thirties", etc.
        $s = preg_replace_callback(
            '/\b(?:number|no\.|#)\s*([a-z][a-z \-]+)\b/iu',
            function ($m) {
                $phrase = $this->normalizeNumberWord($m[1]);   // e.g., "thirties" -> "thirty"
                $n = $this->wordsToNumber($phrase);            // e.g., "thirty" -> 30
                return 'number ' . $n;
            },
            $s
        ) ?? $s;

        // Tidy cases like "number 16's" / "number 3s" -> "number 16" / "number 3"
        $s = preg_replace('/\b(?:number|no\.|#)\s*(\d+)\s*(?:\'s|’s|s|es)\b/iu', 'number $1', $s) ?? $s;

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
            'one'=>1,'two'=>2,'to'=>2,'too'=>2,'three'=>3,'four'=>4,'for'=>4,
            'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,'ten'=>10,
            'eleven'=>11,'twelve'=>12,
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
