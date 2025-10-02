<?php


namespace Tests\Unit\Order;


use PHPUnit\Framework\TestCase;
use App\Services\Order\TextNormalizerImpl;


final class TextNormalizerImplTest extends TestCase
{
    public function test_number_words_to_digits_and_verb_mapping(): void
    {
        $N = new TextNormalizerImpl();
        $out = $N->normalizeCommand("I'd like number thirty-one with bacon.");
        $this->assertStringContainsString('add ', $out);
        $this->assertStringContainsString('number 31', $out);
    }


    public function test_lexify_and_singularize_tokens(): void
    {
        $N = new TextNormalizerImpl();
        $norm = $N->normName('Cheeseburgers'); // → "cheese burger"
        $this->assertSame('cheese burger', $norm);
    }
}
