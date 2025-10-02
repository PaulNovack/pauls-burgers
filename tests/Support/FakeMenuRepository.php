<?php

namespace Tests\Support;

use App\Services\Order\Contracts\MenuRepository;

/**
 * Test-only MenuRepository that loads the full menu from config/menu.php
 * without booting the Laravel application.
 *
 * It directly requires the PHP config file and returns items keyed by id,
 * preserving fields like id, name, type, category, size, price, toppings.
 */
final class FakeMenuRepository implements MenuRepository
{
    /** @var array<int, array> */
    private array $cache;

    /**
     * @param string|null $projectRoot Optional override to locate config/menu.php
     */
    public function __construct(?string $projectRoot = null)
    {
        $this->cache = $this->loadFromConfig($projectRoot);
    }

    /** @return array<int, array> keyed by id */
    public function menu(): array
    {
        return $this->cache;
    }

    /** @return array<int, array> */
    private function loadFromConfig(?string $projectRoot): array
    {
        // Resolve project root relative to tests/Support → tests → <root>
        $root = $projectRoot ?: \dirname(__DIR__, 2);
        $file = $root . '/config/menu.php';

        // Fallback to CWD (handy when running phpunit from a different dir)
        if (!\is_file($file)) {
            $alt = \getcwd() . '/config/menu.php';
            if (\is_file($alt)) {
                $file = $alt;
            } else {
                // Nothing to load
                return [];
            }
        }

        /** @var array<string, mixed> $cfg */
        $cfg = require $file;
        $items = $cfg['items'] ?? [];

        $out = [];
        foreach ($items as $m) {
            $id = (int)($m['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            // Ensure 'id' is present and integer; keep all other fields as-is
            $out[$id] = $m + ['id' => $id];
        }
        return $out;
    }
}
