<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

class OrderService
{
    public function __construct(
        private readonly Session $session,
        private readonly string $key = 'user.order.lines'
    ) {}

    /** Replace with your full catalog source if you wish (DB/config/file). */
    private function catalog(): array
    {
        // Minimal demo catalog; add all your items here or load from config/menu.php, etc.
        $items = config('menu.items');
        return collect($items)->keyBy('id')->all();
    }

    private array $KNOWN_TOPPINGS = [
        '2 beef patties','american cheese','avocado','bacon','bbq sauce','beef patty',
        'blue cheese crumbles','caramelized onions','cheddar cheese','chipotle mayo',
        'grilled mushrooms','jalapeños','ketchup','lettuce','mustard','onion','onion rings',
        'pepper jack cheese','pickles','quarter pound beef patty','swiss cheese','tomato','veggie patty',
    ];

    // ---------------- Session helpers ----------------
    public function all(): array
    {
        return $this->session->get($this->key, []);
    }

    private function save(array $lines): void
    {
        $this->session->put($this->key, array_values($lines));
    }

    public function clear(): array
    {
        $this->save([]);
        return [];
    }

    // --------------- Public command entry ---------------
    /**
     * Supports:
     *  - "add 2 #20 large"
     *  - "add #3 with ketchup and mustard"
     *  - "add a number 3 without onions" / "no onions"
     *  - "remove one #20" (basic)
     *  - "clear order" / "reset order"
     */
    public function processCommand(string $text): array
    {
        $raw = trim((string) $text);
        if ($raw === '') return ['action' => 'noop', 'items' => $this->all()];

        $t = mb_strtolower($raw);

        if ($this->isClearCommand($t)) {
            return ['action' => 'clear', 'items' => $this->clear()];
        }

        // REMOVE (simple form)
        if (preg_match('/^\s*(remove|delete|minus|drop)\b/u', $t)) {
            [$qty, $id] = $this->extractQtyAndId($t);
            $size   = $this->extractSize($t);
            $add    = []; // removing ignores add/remove variants for simplicity
            $remove = [];

            if ($id === null) return ['action' => 'noop', 'items' => $this->all()];
            $this->decrementLine($id, $size, max(1, $qty ?? 1));
            return ['action' => 'remove', 'items' => $this->all()];
        }

        // ADD (default)
        if (preg_match('/^\s*(i want|i would like|add|plus|give me|include|can i have|get me|yeah)\b/u', $t)) {
            [$qty, $id] = $this->extractQtyAndId($t);
            $qty    = max(1, $qty ?? 1);
            $size   = $this->extractSize($t);
            $add    = $this->extractToppings($t, '(?:with|add)\s+');
            $remove = array_merge(
                $this->extractToppings($t, '(?:without|no)\s+'),
                $this->extractToppings($t, 'hold\s+')
            );

            if ($id === null) return ['action' => 'noop', 'items' => $this->all()];

            $this->addLine($id, $qty, $size, $add, $remove);
            return ['action' => 'add', 'items' => $this->all()];
        }

        // Fallback: try to interpret “add … #id …”
        [$qty, $id] = $this->extractQtyAndId($t);
        if ($id !== null) {
            $qty    = max(1, $qty ?? 1);
            $size   = $this->extractSize($t);
            $add    = $this->extractToppings($t, '(?:with|add)\s+');
            $remove = array_merge(
                $this->extractToppings($t, '(?:without|no)\s+'),
                $this->extractToppings($t, 'hold\s+')
            );
            $this->addLine($id, $qty, $size, $add, $remove);
            return ['action' => 'add', 'items' => $this->all()];
        }

        return ['action' => 'noop', 'items' => $this->all()];
    }

    // ---------------- Line mutations ----------------
    private function addLine(int $id, int $qty, ?string $size, array $add, array $remove): void
    {
        $cat = $this->catalog();
        if (!isset($cat[$id])) return;

        $menu = $cat[$id];
        $size = $menu['size'] ?? $size; // prefer catalog default if set

        $lines = $this->all();

        $idx = $this->findMergeIndex($lines, $id, $size, $add, $remove);
        if ($idx !== null) {
            $lines[$idx]['quantity'] += $qty;
        } else {
            $lines[] = [
                'id'       => $id,
                'name'     => $menu['name'],
                'type'     => $menu['type'],
                'category' => $menu['category'] ?? ($menu['type'] === 'drink' ? 'drink' : 'food'),
                'size'     => $size,
                'price'    => (float) $menu['price'],
                'toppings' => null,
                'quantity' => $qty,
                'add'      => $add ?: null,
                'remove'   => $remove ?: null,
            ];
        }

        $this->save($lines);
    }

    private function decrementLine(int $id, ?string $size, int $qty): void
    {
        $lines = $this->all();
        foreach ($lines as $i => $line) {
            if ((int)$line['id'] === $id && (($line['size'] ?? null) === $size || $size === null)) {
                $lines[$i]['quantity'] -= $qty;
                if ($lines[$i]['quantity'] <= 0) {
                    unset($lines[$i]);
                    $lines = array_values($lines);
                }
                break;
            }
        }
        $this->save($lines);
    }

    private function findMergeIndex(array $lines, int $id, ?string $size, array $add, array $remove): ?int
    {
        $norm = fn($arr) => array_values(array_unique(array_map([$this,'normTopping'], $arr)));
        $a = $norm($add); sort($a);
        $r = $norm($remove); sort($r);

        foreach ($lines as $i => $line) {
            if ((int)$line['id'] !== $id) continue;
            if (($line['size'] ?? null) !== $size) continue;

            $la = $norm($line['add'] ?? []); sort($la);
            $lr = $norm($line['remove'] ?? []); sort($lr);

            if ($la === $a && $lr === $r) return $i;
        }
        return null;
    }

    // ---------------- Parsers ----------------
    private function isClearCommand(string $t): bool
    {
        return (bool) preg_match('/\b(clear|reset|new)\s+(order|cart)\b/u', $t);
    }

    /** Returns [qty|null, id|null] */
    private function extractQtyAndId(string $t): array
    {
        $qty = null;

        // digits before "#" → "add 2 #20"
        if (preg_match('/\badd\s+(\d+)\b/u', $t, $m)) {
            $qty = (int) $m[1];
        } else {
            // word-numbers (e.g., "add two #20")
            [$wqty] = $this->extractLeadingWordNumber($t);
            if ($wqty !== null) $qty = $wqty;
        }

        // #id or "number 3"
        $id = null;
        if (preg_match('/#\s*(\d+)/u', $t, $m)) {
            $id = (int) $m[1];
        } elseif (preg_match('/\bnumber\s+(\d+)/u', $t, $m)) {
            $id = (int) $m[1];
        }

        return [$qty, $id];
    }

    private function extractSize(string $t): ?string
    {
        if (preg_match('/\blarge\b/u', $t))   return 'Large';
        if (preg_match('/\bregular\b/u', $t)) return 'Regular';
        return null;
    }

    private function extractToppings(string $t, string $prefix): array
    {
        if (!preg_match('/'.$prefix.'([^.,;]+)/u', $t, $m)) return [];
        $chunk = $m[1];
        $parts = preg_split('/\s*(?:,|and)\s*/u', $chunk) ?: [];
        $parts = array_map('trim', $parts);

        $known = $this->KNOWN_TOPPINGS;
        $norm = fn($s) => preg_replace('/\s+/', ' ', trim(mb_strtolower($s)));
        $out  = [];

        foreach ($parts as $p) {
            $np = $norm($p);
            if ($np === '' || in_array($np, ['with','add','no','without','hold'], true)) continue;
            // exact allow-list match
            foreach ($known as $k) {
                if ($np === $k) { $out[] = $this->title($k); continue 2; }
            }
            // single-word fallback (e.g., "ketchup")
            if (in_array($np, $known, true)) $out[] = $this->title($np);
        }

        return array_values(array_unique($out));
    }

    private function normTopping(string $t): string
    {
        return $this->title(mb_strtolower(trim($t)));
    }

    private function title(string $s): string
    {
        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }

    /** crude word-number parser at the start; returns [qty|null, rest?] (rest unused here) */
    private function extractLeadingWordNumber(string $s): array
    {
        $tokens = preg_split('/\s+/u', trim($s)) ?: [];
        if (!$tokens) return [null, $s];

        $units = [
            'zero'=>0,'one'=>1,'two'=>2,'to'=>2,'three'=>3,'four'=>4,'for'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,
            'ten'=>10,'eleven'=>11,'twelve'=>12,'thirteen'=>13,'fourteen'=>14,'fifteen'=>15,'sixteen'=>16,'seventeen'=>17,'eighteen'=>18,'nineteen'=>19,
        ];
        $tens = ['twenty'=>20,'thirty'=>30,'forty'=>40,'fifty'=>50,'sixty'=>60,'seventy'=>70,'eighty'=>80,'ninety'=>90];
        $scales = ['hundred'=>100,'thousand'=>1000];

        $i=0; $acc=0; $used=false;
        while ($i < count($tokens)) {
            $w = mb_strtolower($tokens[$i]);
            if ($w === 'and') { $i++; continue; }
            if (isset($units[$w])) { $acc += $units[$w]; $i++; $used=true; continue; }
            if (isset($tens[$w]))  { $acc += $tens[$w];  $i++; $used=true; continue; }
            if (isset($scales[$w])){ $acc = max(1,$acc) * $scales[$w]; $i++; $used=true; continue; }
            break;
        }
        if (!$used) return [null, $s];
        $rest = implode(' ', array_slice($tokens, $i));
        return [$acc, $rest];
    }
}
