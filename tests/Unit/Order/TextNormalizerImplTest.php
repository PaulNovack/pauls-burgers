<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use Tests\TestCase;
use App\Services\Order\TextNormalizerImpl;

final class TextNormalizerImplTest extends TestCase
{
    private TextNormalizerImpl $N;

    protected function setUp(): void
    {
        parent::setUp();
        $this->N = new TextNormalizerImpl();
    }

    /** Polite → add */
    public function test_could_you_give_me_becomes_add(): void
    {
        $in  = "Could you give me some tater tots?";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add tater tots', $out);
    }

    /** Just add me some → add */
    public function test_just_add_me_some(): void
    {
        $in  = "Just add me some onion rings";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add onion rings', $out);
    }

    /** Y'all thinking you could add me some → add */
    public function test_yall_thinking_add_me(): void
    {
        $in  = "Y'all thinking you could add me some onion rings";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add onion rings', $out);
    }

    /** Well, I decided I want → add */
    public function test_decided_i_want(): void
    {
        $in  = "Well, I decided I want onion rings.";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add onion rings', $out);
    }

    /** Yeah, and add me some → add */
    public function test_yeah_and_add_me_some(): void
    {
        $in  = "Yeah, and add me some pickle chips";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add pickle chips', $out);
    }

    /** Can y'all give me some → add */
    public function test_can_yall_give_me(): void
    {
        $in  = "Can y'all give me some chili cheese fries?";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add chili cheese fries', $out);
    }

    /** Remove 'orders of' while keeping qty */
    public function test_add_orders_of_name_only(): void
    {
        $in  = "Add orders of curly fries with ketchup";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add curly fries with ketchup', $out);
    }

    public function test_add_two_orders_of_name_only(): void
    {
        $in  = "Add two orders of tater tots with ketchup";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add two tater tots with ketchup', $out);
    }

    /** One of them number threes → number 3 */
    public function test_one_of_them_number_threes(): void
    {
        $in  = "Add me one of them number threes";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add number 3', $out);
    }

    /** ASR: "at a number five" → "add number 5" */
    public function test_at_a_number_five_without_onion(): void
    {
        $in  = "at a number five without onion.";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add number 5 without onion', $out);
    }

    /** with no X / no X → without X */
    public function test_with_no_and_no_become_without(): void
    {
        $in1 = "Had a lemonade with no ice";
        $out1 = $this->N->normalizeCommand($in1);
        $this->assertSame('add lemonade without ice', $out1);

        $in2 = "Had a lemonade no ice";
        $out2 = $this->N->normalizeCommand($in2);
        $this->assertSame('add lemonade without ice', $out2);
    }

    /** "add like two ..." → "add two ..." */
    public function test_add_like_two(): void
    {
        $in  = "add like two onion rings";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add two onion rings', $out);
    }

    /** collapse "add and add"/"add add" */
    public function test_collapse_duplicate_add(): void
    {
        $in1  = "add and add fries";
        $out1 = $this->N->normalizeCommand($in1);
        $this->assertSame('add fries', $out1);

        $in2  = "add add fries";
        $out2 = $this->N->normalizeCommand($in2);
        $this->assertSame('add fries', $out2);
    }

    /** strip trailing punctuation */
    public function test_strip_trailing_punctuation(): void
    {
        $in  = "add fries!!!";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add fries', $out);
    }

    /** plural markers after number → singular number */
    public function test_number_plural_markers(): void
    {
        $in1  = "add number 3s";
        $out1 = $this->N->normalizeCommand($in1);
        $this->assertSame('add number 3', $out1);

        $in2  = "add number 3's";
        $out2 = $this->N->normalizeCommand($in2);
        $this->assertSame('add number 3', $out2);
    }

    /** determiners after add are trimmed */
    public function test_clean_determiners_after_add(): void
    {
        $in  = "add the some a an fries";
        $out = $this->N->normalizeCommand($in);
        $this->assertSame('add fries', $out);
    }
}
