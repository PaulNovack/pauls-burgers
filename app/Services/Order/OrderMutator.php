<?php
namespace App\Services\Order;

use App\Services\Order\Contracts\{OrderRepository, MenuRepository, TextNormalizer, ModifierResolver};
use App\Services\Order\Dto\{ParsedCommand, AddById, AddByName, RemoveById, RemoveByName};

final class OrderMutator
{
    public function __construct(
        private readonly OrderRepository $repo,
        private readonly MenuRepository $menu,
        private readonly TextNormalizer $N,
        private readonly ModifierResolver $mods,
    ) {}

    public function all(): array { return array_values($this->repo->all()); }
    public function clear(): array { return $this->repo->clear(); }

    /** @return array{action:string,items:array} */
    public function apply(ParsedCommand|string|null $cmd): array
    {
        if ($cmd === null)   return ['action' => 'noop',   'items' => $this->all()];
        if ($cmd === 'clear') return ['action' => 'clear',  'items' => $this->clear()];

        return match (get_class($cmd)) {
            AddById::class      => $this->applyAddById($cmd),
            AddByName::class    => $this->applyAddByName($cmd),
            RemoveById::class   => $this->applyRemoveById($cmd),
            RemoveByName::class => $this->applyRemoveByName($cmd),
            default             => ['action' => 'noop', 'items' => $this->all()],
        };
    }

    private function applyAddById(AddById $c): array
    {
        $ok = $c->id > 0 ? $this->addByMenuId($c->id, $c->qty, $c->add, $c->remove) : false;
        return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
    }

    private function applyAddByName(AddByName $c): array
    {
        $ok = $this->addByName($c->name, $c->qty, $c->add, $c->remove, $c->size);
        return ['action' => $ok ? 'add' : 'noop', 'items' => $this->all()];
    }

    private function applyRemoveById(RemoveById $c): array
    {
        $ok = $c->id > 0
            ? $this->decrementByIdWithMods($c->id, $c->qty, $c->size, $c->needAdd, $c->needRemove)
            : false;
        return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
    }

    private function applyRemoveByName(RemoveByName $c): array
    {
        $id = (new \App\Services\Order\Parsing\NameMatcher($this->menu, $this->N))
            ->findMenuIdByName($c->name, $c->size);
        $ok = $id
            ? $this->decrementByIdWithMods($id, $c->qty, $c->size, $c->needAdd, $c->needRemove)
            : false;

        return ['action' => $ok ? 'remove' : 'noop', 'items' => $this->all()];
    }

    // ---------- internals (unchanged semantics from your original) ----------

    private function addByMenuId(int $id, int $qty, array $add, array $remove): bool
    {
        $menu = $this->menu->menu();
        $menuItem = $menu[$id] ?? null;
        if (!$menuItem) return false;

        $categoryOrType = $menuItem['type'] ?? ($menuItem['category'] ?? '');
        // Filter modifiers by category/type
        $add    = $this->mods->filterByCategory((string)$categoryOrType, $add);
        $remove = $this->mods->filterByCategory((string)$categoryOrType, $remove);

        $items = $this->repo->all();
        $key = $this->lineKey($id, $menuItem['size'] ?? null, $add, $remove);

        if (!isset($items[$key])) {
            $items[$key] = $this->makeLine($menuItem, $qty, $add, $remove);
        } else {
            $items[$key]['quantity'] = max(1, (int)$items[$key]['quantity'] + max(1, $qty));
            // de-dup adds/removes
            $items[$key]['add']    = $this->uniqueList(array_merge($items[$key]['add'] ?? [], $add));
            $items[$key]['remove'] = $this->uniqueList(array_merge($items[$key]['remove'] ?? [], $remove));
        }
        $this->repo->putAll($items);
        return true;
    }

    private function addByName(string $spokenName, int $qty, array $add, array $remove, ?string $size = null): bool
    {
        $matcher = new \App\Services\Order\Parsing\NameMatcher($this->menu, $this->N);
        $id = $matcher->findMenuIdByName($spokenName, $size);
        return $id ? $this->addByMenuId($id, $qty, $add, $remove) : false;
    }

    /** Decrement by id, optionally filtering by size/add/remove. Returns true if anything changed. */
    private function decrementByIdWithMods(int $id, int $qty, ?string $size, array $needAdd, array $needRemove): bool
    {
        $items = $this->repo->all();
        if (empty($items) || $qty <= 0) return false;

        // Determine category/type for this menu id (used to filter allowed modifiers)
        $menu = $this->menu->menu();
        $menuItem = $menu[$id] ?? null;
        $categoryOrType = $menuItem['type'] ?? ($menuItem['category'] ?? '');

        // Canonicalize + category-filter the requested modifier constraints
        $needAdd    = $this->mods->filterByCategory((string)$categoryOrType, $this->mods->resolveList($needAdd));
        $needRemove = $this->mods->filterByCategory((string)$categoryOrType, $this->mods->resolveList($needRemove));

        // Collect candidate line indexes that match id/size and contain the requested mods
        $candidates = [];
        foreach ($items as $k => $line) {
            if ((int)($line['id'] ?? 0) !== $id) continue;

            // size match (if requested)
            if ($size !== null && $this->N->normalizeSize($line['size'] ?? null) !== $size) continue;

            // requested modifiers must be subsets of the line's modifiers
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
            $cur  = max(1, (int)($items[$k]['quantity'] ?? 1));
            $take = min($cur, $remaining);
            $new  = $cur - $take;

            if ($new <= 0) {
                unset($items[$k]);
            } else {
                $items[$k]['quantity'] = $new;
            }

            $remaining -= $take;
            if ($remaining <= 0) break;
        }

        // Reindex and persist
        $this->repo->putAll(array_values($items));
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
        if (preg_match('/(.*[^aeiou])ies$/u', $s, $m)) return $m[1].'y';   // onions -> onion
        if (preg_match('/(.*)s$/u', $s, $m)) return $m[1];                 // rings -> ring
        return $s;
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
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = mb_convert_case($x, MB_CASE_TITLE, 'UTF-8');
        }
        return $out;
    }

    /** Guarantees “same id + same size + same modifiers” is merged. */
    private function lineKey(int $id, ?string $size, array $add, array $remove): string
    {
        sort($add, SORT_NATURAL | SORT_FLAG_CASE);
        sort($remove, SORT_NATURAL | SORT_FLAG_CASE);
        return $id . '|' . ($size ?? 'none') . '|' . implode(',', $add) . '|' . implode(',', $remove);
    }
}
