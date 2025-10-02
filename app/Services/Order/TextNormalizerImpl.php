<?php
namespace App\Services\Order;

use App\Services\Order\Contracts\TextNormalizer;
use Illuminate\Support\Facades\Log;

final class TextNormalizerImpl implements TextNormalizer
{
    // app/Services/Order/TextNormalizerImpl.php


    public function normalizeCommand(string $s): string
    {
        $s = trim($s ?? '');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        Log::info('norm #1', [$s]);

        // strip trailing punctuation
        $s = preg_replace('/[.!?]+$/u', '', $s) ?? $s;

        // light fillers at the very start
        $s = preg_replace(
            '/^\s*(?:well|you know|ya know|so|hey)[, ]+/iu',
            '',
            $s
        ) ?? $s;
        Log::info('norm #2a', [$s]);

        // map lead-ins → "add "
        $s = preg_replace(
            '/^\s*(?:and\s+at|and|also|plus|yeah|yep|ok|okay|uh|um|please|then'.
            '|at|i\s+want|i\'?d\s+like|i\s+would\s+like|i\'?ll\s+have|give\s+me'.
            '|gimme|include|i\s+need|could\s+i\s+have|can\s+i\s+get|may\s+i\s+have'.
            '|could\s+you\s+(?:give\s+me|add)|can\s+you\s+(?:give\s+me|add)'.
            '|would\s+you\s+(?:give\s+me|add)'.
            '|y[\'’]?all\s+(?:think(?:ing)?\s+you\s+could|can|could)\s+add'.
            '(?:\s+me)?'.
            '|(?:i\s+had|had)\s+(?:a|an))\b[,:-]?\s*/iu',
            'add ',
            $s
        ) ?? $s;

        // extra polite/variant starters
        $s = preg_replace(
            '/^\s*(?:could|can)\s+(?:you|y[\'’]?all)\s+give\s+me\s+(?:some\s+)?/iu',
            'add ',
            $s
        ) ?? $s;
        $s = preg_replace('/^\s*just\s+add\s+me\s+(?:some\s+)?/iu', 'add ', $s) ?? $s;
        $s = preg_replace(
            '/^\s*y[\'’]?all\s+(?:think(?:ing)?\s+you\s+could|can|could)\s+add'.
            '\s+me\s+(?:some\s+)?/iu',
            'add ',
            $s
        ) ?? $s;
        $s = preg_replace(
            '/^\s*(?:yeah|yep|ok|okay)\s*,?\s*(?:and\s+)?add\s+me\s+(?:some\s+)?/iu',
            'add ',
            $s
        ) ?? $s;
        $s = preg_replace(
            '/^\s*(?:well[, ]+)?i\s+decided\s+i\s+want\s+/iu',
            'add ',
            $s
        ) ?? $s;
        Log::info('norm #3', [$s]);

        // clean determiners/junk after "add"
        $s = preg_replace(
            '/^\s*add\s+(?:(?:and|in|on|to|for|please|me|us|the|a|an|some|'.
            'order|orders|of|them)\s+)+/iu',
            'add ',
            $s
        ) ?? $s;

        // collapse "add and add"/"add add"
        $s = preg_replace('/^\s*add\s+(?:and\s+)?add\s+/iu', 'add ', $s) ?? $s;

        // "add like two ..." → "add two ..."
        $s = preg_replace(
            '/\badd\s+like\s+(?=(?:one|two|to|too|three|four|for|five|six|seven'.
            '|eight|nine|ten|\d+)\b)/iu',
            'add ',
            $s
        ) ?? $s;

        // ASR: "at a number ..." → "add number ..."
        $s = preg_replace('/^\s*at\s+(?:a\s+)?number\b/iu', 'add number ', $s) ?? $s;

        // "one of them number threes" → "number threes"
        $s = preg_replace('/\bone\s+of\s+them\s+(?=number\b)/iu', '', $s) ?? $s;

        // "number of 5" → "number 5"
        $s = preg_replace('/\bnumber\s+of\s+/iu', 'number ', $s) ?? $s;

        // "add <qty> orders of X" → "add <qty> X"
        $s = preg_replace(
            '/\b(add\s+(?:\d+|one|two|to|too|three|four|for|five|six|seven|'.
            'eight|nine|ten|eleven|twelve)\s+)(?:orders?|order)\s+of\b/iu',
            '$1',
            $s
        ) ?? $s;
        // "add orders of X" → "add X"
        $s = preg_replace('/\badd\s+(?:orders?|order)\s+of\b/iu', 'add ', $s) ?? $s;

        // negations: "with no" / "no X" → "without X"
        $s = preg_replace('/\bwith\s+no\s+/iu', ' without ', $s) ?? $s;
        $s = preg_replace('/\bno\s+(?=[a-z])/iu', 'without ', $s) ?? $s;

        Log::info('norm #4', [$s]);

        // number words after "number" → digits
        $s = preg_replace_callback(
            '/\b(?:number|no\.|#)\s*('.
            '(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|'.
            'ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|'.
            'eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|'.
            'ninety|hundred|thousand)'.
            '(?:[-\s]+'.
            '(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|'.
            'ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|'.
            'eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|'.
            'ninety|hundred|thousand)'.
            ')*'.
            ')\b/iu',
            function ($m) {
                $phrase = $this->normalizeNumberWord($m[1]);
                $n = $this->wordsToNumber($phrase);
                return 'number '.$n;
            },
            $s
        ) ?? $s;

        // "number 3s/3's/3es" → "number 3"
        $s = preg_replace(
            '/\b(?:number|no\.|#)\s*(\d+)\s*(?:\'s|’s|s|es)\b/iu',
            'number $1',
            $s
        ) ?? $s;

        Log::info('norm #5', [$s]);

        // keep command text un-lexified (names are lexified on the name side)
        return $s;
    }



    public function normalizeSize(?string $size): ?string
    {
        if(!$size) return null;
        $s=mb_strtolower(trim($size));
        return match($s){ 'small'=>'Small','regular'=>'Regular','large'=>'Large', default=>null};
    }

    public function normName(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = $this->stripDiacritics($s);
        $s = $this->lexifyName($s);
        $s = str_replace('-',' ',$s);
        $s = preg_replace('/[^\p{L}\p{N}& ]+/u',' ',$s) ?? $s;
        $s = preg_replace('/\s+/u',' ',$s) ?? $s;
        $tokens = array_filter(explode(' ',$s));
        $tokens = array_map([$this,'singularize'],$tokens);
        return implode(' ',$tokens);
    }

    public function stripDiacritics(string $s): string
    {
        $from=['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ','’','ʼ','ʻ','ˈ'];
        $to  =['a','e','i','o','u','n','a','e','i','o','u','n','\'','\'','\'','\''];
        return str_replace($from,$to,$s);
    }

    private function singularize(string $w): string
    {
        if (preg_match('/(.*[^aeiou])ies$/u',$w,$m)) return $m[1].'y';
        if (preg_match('/(.*)es$/u',$w,$m)) return $m[1];
        if (preg_match('/(.*)s$/u',$w,$m))  return $m[1];
        return $w;
    }

    /** Minimal lexify for COMMAND strings */
    private function lexifyCommand(string $s): string
    {
        $s = preg_replace('/\bbarbe?cue\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bbar[-\s]*b[-\s]*q\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bb\.?\s*b\.?\s*q\.?\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bmilk[-\s]*shake\b/iu','milkshake',$s) ?? $s;
        $s = preg_replace('/\bjalapen(?:o|os)\b/iu','jalapeno',$s) ?? $s;
        // intentionally leave mac & cheese / coke / cheeseburger rewrites for name-side only
        return $s;
    }

    /** Aggressive lexify for NAMES (menu matching) */
    private function lexifyName(string $s): string
    {
        $s = $this->lexifyCommand($s);
        $s = preg_replace('/\bmac\s*(?:and|&|n[\'’]?)\s*cheese\b/iu','mac & cheese',$s) ?? $s;
        $s = preg_replace('/\bcoke\b/iu','coca cola',$s) ?? $s;
        $s = preg_replace('/\bcheeseburgers\b/iu','cheese burgers',$s) ?? $s;
        $s = preg_replace('/\bcheeseburger\b/iu','cheese burger',$s) ?? $s;
        return $s;
    }

    private function normalizeNumberWord(string $s): string { return (new \App\Services\Order\Parsing\NumberWordConverter)->normalizeNumberWord($s); }
    private function wordsToNumber(string $s): int          { return (new \App\Services\Order\Parsing\NumberWordConverter)->wordsToNumber($s); }
}
