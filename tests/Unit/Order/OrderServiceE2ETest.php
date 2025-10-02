<?php

namespace Tests\Unit\Order;

use PHPUnit\Framework\TestCase;
use Tests\Support\{InMemoryOrderRepository, FakeMenuRepository};
use App\Services\Order\{OrderService, OrderMutator, TextNormalizerImpl};
use App\Services\Order\Parsing\{CommandParser, NumberWordConverter, NameMatcher};
use App\Services\Order\Impl\DefaultModifierResolver;

final class OrderServiceE2ETest extends TestCase
{
    private function buildService(): OrderService
    {
        $repo    = new InMemoryOrderRepository();
        $menu    = new FakeMenuRepository();
        $norm    = new TextNormalizerImpl(null, new NumberWordConverter());
        $parser  = new CommandParser(
            $norm,
            new NumberWordConverter(),
            new NameMatcher($menu, $norm),
            new DefaultModifierResolver(),
        );
        $mutator = new OrderMutator($repo, $menu, $norm, new DefaultModifierResolver());

        return new OrderService($repo, $parser, $mutator);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('addPhrases')]
    public function test_add_phrases_end_to_end(string $phrase, int $expectedCount): void
    {
        $svc  = $this->buildService();
        $resp = $svc->processCommand($phrase);

        $this->assertSame('add', $resp['action']);
        $qty = array_sum(array_column($resp['items'], 'quantity'));
        $this->assertSame($expectedCount, $qty);
    }

    public static function addPhrases(): array
    {
        return [
            ['add two number sixteen with bacon', 2],
            ["i'd like a large lemonade", 1],
            ['plus onion rings without ketchup', 1],
        ];
    }

    public function test_clear_then_noop(): void
    {
        $svc = $this->buildService();
        $svc->processCommand('add lemonade');

        $resp = $svc->processCommand('clear order');
        $this->assertSame('clear', $resp['action']);
        $this->assertSame([], $resp['items']);

        $resp2 = $svc->processCommand('gibberish command');
        $this->assertSame('noop', $resp2['action']);
    }

    public function test_remove_phrases_end_to_end(): void
    {
        $svc = $this->buildService();
        $svc->processCommand('add two veggie burgers');

        $resp = $svc->processCommand('remove one veggie burger');
        $this->assertSame('remove', $resp['action']);
        $this->assertSame(1, $resp['items'][0]['quantity']);
    }
}
