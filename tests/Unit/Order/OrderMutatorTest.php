<?php


namespace Tests\Unit\Order;


use PHPUnit\Framework\TestCase;
use Tests\Support\{InMemoryOrderRepository, FakeMenuRepository};
use App\Services\Order\{OrderMutator, TextNormalizerImpl};
use App\Services\Order\Impl\DefaultModifierResolver;
use App\Services\Order\Dto\{AddById, AddByName, RemoveById, RemoveByName};


final class OrderMutatorTest extends TestCase
{
    private function mutator(): OrderMutator
    {
        return new OrderMutator(
            new InMemoryOrderRepository(),
            new FakeMenuRepository(),
            new TextNormalizerImpl(),
            new DefaultModifierResolver(),
        );
    }


    public function test_add_and_merge_same_line(): void
    {
        $m = $this->mutator();
        $m->apply(new AddById(3, 1, [], [])); // Regular Lemonade
        $resp2 = $m->apply(new AddById(3, 2, [], [])); // merge quantities
        $this->assertSame('add', $resp2['action']);
        $this->assertCount(1, $resp2['items']);
        $this->assertSame(3, $resp2['items'][0]['quantity']);
    }


    public function test_add_by_name_with_size_and_mods(): void
    {
        $m = $this->mutator();
        $r = $m->apply(new AddByName('lemonade', 1, ['Bacon'], [], 'Large'));
        $this->assertSame('add', $r['action']);
        $this->assertCount(1, $r['items']);
        $this->assertSame(46, $r['items'][0]['id']); // Large lemonade id=4
        $this->assertContains('Bacon', $r['items'][0]['add']);
    }


    public function test_remove_by_id_with_filters(): void
    {
        $m = $this->mutator();
        $m->apply(new AddById(1, 2, ['Onion'], ['Tomato']));
        $m->apply(new AddById(1, 1, [], []));
        $r = $m->apply(new RemoveById(1, 1, null, ['Onion'], ['Tomato']));
        $this->assertSame('remove', $r['action']);
        $this->assertCount(2, $r['items']); // one decremented from the specific line
    }


    public function test_remove_by_name_path(): void
    {
        $m = $this->mutator();
        $m->apply(new AddByName('veggie burger', 2, [], [], null));
        $r = $m->apply(new RemoveByName('veggie burger', 1, null, [], []));
        $this->assertSame('remove', $r['action']);
        $this->assertSame(1, $r['items'][0]['quantity']);
    }
}
