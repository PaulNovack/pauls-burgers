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
        $s = preg_replace('/[.!?]+$/u', '', $s) ?? $s;

        // drop light fillers at the very start
        $s = preg_replace('/^\s*(?:well|you know|ya know|so|hey)[, ]+/iu', '',
            $s) ?? $s;

        // polite / helper forms → "add "
        $s = preg_replace('/^\s*(?:could|can)\s+(?:you|y[\'’]?all)\s+give\s+me'
            . '\s+(?:some\s+)?/iu', 'add ', $s) ?? $s;
        $s = preg_replace('/^\s*just\s+add\s+me\s+(?:some\s+)?/iu',
            'add ', $s) ?? $s;
        $s = preg_replace('/^\s*just\s+have\s+me\s+(?:a\s+|an\s+)?/iu',
            'add ', $s) ?? $s;
        $s = preg_replace('/^\s*y[\'’]?all\s+(?:think(?:ing)?\s+you\s+could|can'
            . '|could)\s+add\s+me\s+(?:some\s+)?/iu',
            'add ', $s) ?? $s;
        $s = preg_replace('/^\s*(?:well[, ]+)?i\s+decided\s+i\s+want\s+/iu',
            'add ', $s) ?? $s;
        $s = preg_replace('/^\s*(?:and|also|plus|yeah|yep|ok|okay|uh|um|please|'
            . 'then|at|i\s+want|i\'?d\s+like|i\s+would\s+like|'
            . 'i\'?ll\s+have|give\s+me|gimme|include|could\s+i\s+'
            . 'have|can\s+i\s+get|may\s+i\s+have|i\s+need)\b[,:-]?'
            . '\s*/iu', 'add ', $s) ?? $s;

        // past-tense "had a/an …"
        $s = preg_replace('/^\s*(?:i\s+had|had)\s+(?:a|an)\s+/iu',
            'add ', $s) ?? $s;

        // misheard "at a number (of) …"
        $s = preg_replace('/^\s*at\s+(?:a\s+)?number\s+(?:of\s+)?/iu',
            'add number ', $s) ?? $s;

        // "add me one of them …" / "add one of them …"
        $s = preg_replace('/^\s*add\s+(?:me\s+)?one\s+of\s+them\s+/iu',
            'add ', $s) ?? $s;

        // clean determiners right after "add"
        $s = preg_replace('/^\s*add\s+(?:(?:in|on|to|for|please|me|us|the|a|an|'
            . 'some)\s+)+/iu', 'add ', $s) ?? $s;

        // quantities and counts
        $s = preg_replace('/\badd\s+like\s+(?=(?:\d+|one|two|to|too|three|four|'
            . 'for|five|six|seven|eight|nine|ten|eleven|twelve)\b)'
            . '/iu', 'add ', $s) ?? $s;
        $s = preg_replace('/\badd\s+(?:to|too)\b/iu', 'add two', $s) ?? $s;
        $s = preg_replace('/\badd\s+(?:a\s+)?couple\s+of\s+/iu',
            'add two ', $s) ?? $s;

        // drop "orders of" after optional qty
        $s = preg_replace('/\badd\s+((?:\d+|one|two|to|too|three|four|for|five|'
            . 'six|seven|eight|nine|ten|eleven|twelve)\s+)?(?:a\s+)?'
            . 'orders?\s+of\s+/iu', 'add ${1}', $s) ?? $s;

        // "#16s" → "number 16" (digits)
        $s = preg_replace('/\b(?:number|no\.|#)\s*(\d+)\s*(?:\'s|’s|s|es)\b/iu',
            'number $1', $s) ?? $s;

        // "number threes" → "number three" (words)
        $s = preg_replace('/\b(?:number|no\.|#)\s+([a-z]+(?:\s+[a-z]+)*)\s*'
            . '(?:\'s|’s|s|es)\b/iu', 'number $1', $s) ?? $s;

        // "number of five" → "number five"
        $s = preg_replace('/\bnumber\s+of\s+(\w+)/iu', 'number $1', $s) ?? $s;

        // number <words> → number <digits>
        $s = preg_replace_callback('/\b(?:number|no\.|#)\s*('
            . '(?:zero|one|two|to|too|three|four|for|five|six|seven|'
            . 'eight|nine|ten|eleven|twelve|thirteen|fourteen|'
            . 'fifteen|sixteen|seventeen|eighteen|nineteen|twenty|'
            . 'thirty|forty|fifty|sixty|seventy|eighty|ninety|'
            . 'hundred|thousand)(?:[-\s]+(?:zero|one|two|to|too|'
            . 'three|four|for|five|six|seven|eight|nine|ten|eleven|'
            . 'twelve|thirteen|fourteen|fifteen|sixteen|seventeen|'
            . 'eighteen|nineteen|twenty|thirty|forty|fifty|sixty|'
            . 'seventy|eighty|ninety|hundred|thousand))*'
            . ')\b/iu',
            function ($m) {
                $p = $this->normalizeNumberWord($m[1]);
                $n = $this->wordsToNumber($p);
                return 'number ' . $n;
            },
            $s
        ) ?? $s;

        // "with no X" / "no X" → "without X"
        $s = preg_replace('/\bwith\s+no\s+/iu', ' without ', $s) ?? $s;
        $s = preg_replace('/\bno\s+(?=[a-z])/iu', 'without ', $s) ?? $s;

        // collapse "add and add …"
        $s = preg_replace('/^\s*add\s+(?:and\s+)?add\s+/iu', 'add ', $s) ?? $s;

        // trim trailing hedge "…, i think"
        $s = preg_replace('/\s*,?\s*i\s+think\s*$/iu', '', $s) ?? $s;

        // light lexify, tidy, lowercase
        $s = $this->lexify($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim(mb_strtolower($s));
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

    private function lexify(string $s): string
    {
        // Normalize common spellings without changing menu names
        $s = preg_replace('/\bbarbe?cue\b/iu', 'bbq', $s) ?? $s;
        $s = preg_replace('/\bbar[-\s]*b[-\s]*q\b/iu', 'bbq', $s) ?? $s;
        $s = preg_replace('/\bb\.?\s*b\.?\s*q\.?\b/iu', 'bbq', $s) ?? $s;
        $s = preg_replace('/\bmilk[-\s]*shake\b/iu', 'milkshake', $s) ?? $s;
        $s = preg_replace('/\bjalapeñ?o?s?\b/iu', 'jalapeno', $s) ?? $s;
        return $s;
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
