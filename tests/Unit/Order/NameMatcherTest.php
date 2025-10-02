<?php


namespace Tests\Unit\Order;


use Tests\TestCase;
use App\Services\Order\Parsing\NameMatcher;
use App\Services\Order\TextNormalizerImpl;
use Tests\Support\FakeMenuRepository;


final class NameMatcherTest extends TestCase
{
    public function test_exact_and_size_preferences(): void
    {
        $matcher = new NameMatcher(new FakeMenuRepository(), new TextNormalizerImpl());
// Exact name, prefers Regular when size not given
        $id = $matcher->findMenuIdByName('lemonade', null);
        $this->assertSame(45, $id);
// Explicit size → Large
        $idLarge = $matcher->findMenuIdByName('lemonade', 'Large');
        $this->assertSame(46, $idLarge);
    }
    public function test_hash_number_plural()
    {
        $n = new TextNormalizerImpl();
        $this->assertSame('Add number 16', $n->normalizeCommand('Add #16s'));
    }

    public function test_token_subset_and_levenshtein(): void
    {
        $matcher = new NameMatcher(new FakeMenuRepository(), new TextNormalizerImpl());
        $id = $matcher->findMenuIdByName('veggie', null); // token subset
        $this->assertSame(7, $id);
        $id2 = $matcher->findMenuIdByName('lemonaed', null); // small typo
        $this->assertNotNull($id2);
    }
}
