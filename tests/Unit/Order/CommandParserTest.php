<?php

namespace Tests\Unit\Order;

use PHPUnit\Framework\TestCase;

use App\Services\Order\TextNormalizerImpl;
use App\Services\Order\Impl\DefaultModifierResolver;
use App\Services\Order\Parsing\{CommandParser, NumberWordConverter, NameMatcher};
use Tests\Support\{FakeMenuRepository};

final class CommandParserTest extends TestCase
{
    private function makeParser(): CommandParser
    {
        $menu = new FakeMenuRepository();
        $norm = new TextNormalizerImpl(null, new NumberWordConverter());
        $mods = new DefaultModifierResolver();

        return new CommandParser(
            $norm,
            new NumberWordConverter(),
            new NameMatcher($menu, $norm),
            $mods
        );
    }

    public function test_clear(): void
    {
        $parser = $this->makeParser();
        $out = $parser->parse('clear order');
        $this->assertSame('clear', $out);
    }

    public function test_add_by_id_words_and_modifiers(): void
    {
        $parser = $this->makeParser();
        $out = $parser->parse('add number two with cheddar and bacon');

        $this->assertInstanceOf(\App\Services\Order\Dto\AddById::class, $out);
        $this->assertSame(1, $out->qty);          // ← was 2
        $this->assertSame(2, $out->id);
        $this->assertContains('Cheddar Cheese', $out->add);
        $this->assertContains('Bacon', $out->add);
        $this->assertSame([], $out->remove);
    }
    public function test_add_by_name_with_size(): void
    {
        $parser = $this->makeParser();
        $out = $parser->parse("add a large lemonade");
        $this->assertInstanceOf(\App\Services\Order\Dto\AddByName::class, $out);
        $this->assertSame('Large', $out->size);
    }

    public function test_remove_by_name_with_modifiers(): void
    {
        $parser = $this->makeParser();
        $out = $parser->parse('remove fries without ketchup');
        $this->assertInstanceOf(\App\Services\Order\Dto\RemoveByName::class, $out);
        $this->assertSame(['Ketchup'], $out->needRemove);
    }
}
