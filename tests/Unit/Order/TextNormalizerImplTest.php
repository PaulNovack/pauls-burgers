<?php
namespace App\Services\Order;

use App\Services\Order\Contracts\TextNormalizer;
use Illuminate\Support\Facades\Log;

final class TextNormalizerImpl implements TextNormalizer
{
    public function normalizeCommand(string $s): string
    {
        $s = trim($s ?? '');
        $s = preg_replace('/\s+/u',' ',$s) ?? $s;
        Log::info('regex #1',[$s]);

        // strip trailing punctuation
        $s = preg_replace('/[.!?]+$/u','',$s) ?? $s;                    Log::info('regex #2',[$s]);

        // “at a …” → add …
        $s = preg_replace('/^\s*(?:and\s+at)\b[,:-]?\s*/iu','add ',$s) ?? $s;  Log::info('regex #3',[$s]);

        // common “pre-add” lead-ins → add …
        $s = preg_replace(
            '/^\s*(?:and|also|plus|yeah|yep|ok|okay|uh|um|please|then|at|'
            . 'i\s+want|i\'?d\s+like|i\s+would\s+like|i\'?ll\s+have|give\s+me|gimme|include|'
            . 'could\s+i\s+have|can\s+i\s+get|may\s+i\s+have|i\s+need|have\s+me|just\s+have\s+me)\b[,:-]?\s*/iu',
            'add ', $s
        ) ?? $s;                                                         Log::info('regex #4',[$s]);

        // remove “add in|on|to|for|please|me|us|the|a|an …”
        $s = preg_replace('/^\s*add\s+(?:(?:in|on|to|for|please|me|us|the|a|an)\s+)+/iu','add ',$s) ?? $s;  Log::info('regex #5',[$s]);

        // “add to …” / “add too …” → “add two …”
        $s = preg_replace('/^\s*add\s+(?:to|too)\b/iu','add two ',$s) ?? $s;    Log::info('regex #6',[$s]);

        // turn “add like …” into “add …”
        $s = preg_replace('/^\s*add\s+like\s+/iu','add ',$s) ?? $s;

        // drop “some ” after add
        $s = preg_replace('/^\s*add\s+some\s+/iu','add ',$s) ?? $s;

        // “add (a) couple of …” → “add two …”
        $s = preg_replace('/^\s*add\s+(?:a\s+)?couple\s+of\s+/iu','add two ',$s) ?? $s;

        // “add <qty> (order|orders|items) of …” → “add <qty> …”
        $s = preg_replace(
            '/^\s*add\s+(?<q>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s+'
            . '(?:orders?|order|items?)\s+of\s+/iu',
            'add $1 ', $s
        ) ?? $s;

        // number words after “number …” → digits
        $s = preg_replace_callback(
            '/\b(?:number|no\.|#)\s*('
            . '(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand)'
            . '(?:[-\s]+'
            . '(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand)'
            . ')*' .')\b/iu',
            function($m){
                $phrase=$this->normalizeNumberWord($m[1]);
                $n=$this->wordsToNumber($phrase);
                return 'number '.$n;
            },
            $s
        ) ?? $s;                                                         Log::info('regex #7',[$s]);

        // “number 5's” → “number 5”
        $s = preg_replace('/\b(?:number|no\.|#)\s*(\d+)\s*(?:\'s|’s|s|es)\b/iu','number $1',$s) ?? $s;  Log::info('regex #8',[$s]);

        // *** IMPORTANT: command lexify must NOT change item wording the tests compare ***
        $s = $this->lexifyCommand($s);

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
        // Name lexify CAN normalize menu variants for matching
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

    /** Minimal lexify for COMMANDS (don’t rewrite names users said) */
    private function lexifyCommand(string $s): string
    {
        $s = preg_replace('/\bbarbe?cue\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bbar[-\s]*b[-\s]*q\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bb\.?\s*b\.?\s*q\.?\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bmilk[-\s]*shake\b/iu','milkshake',$s) ?? $s;
        $s = preg_replace('/\bjalapen(?:o|os)\b/iu','jalapeno',$s) ?? $s;
        // NOTE: no mac&cheese / coke / cheeseburger here
        return $s;
    }

    /** Aggressive lexify for NAMES (for matching against menu) */
    private function lexifyName(string $s): string
    {
        $s = $this->lexifyCommand($s);
        $s = preg_replace('/\bmac\s*(?:and|&|n[\'’]?)\s*cheese\b/iu','mac & cheese',$s) ?? $s;
        $s = preg_replace('/\bcoke\b/iu','coca cola',$s) ?? $s;
        $s = preg_replace('/\bcheeseburgers\b/iu','cheese burgers',$s) ?? $s;
        $s = preg_replace('/\bcheeseburger\b/iu','cheese burger',$s) ?? $s;
        return $s;
    }

    // reuse NumberWordConverter
    private function normalizeNumberWord(string $s): string { return (new \App\Services\Order\Parsing\NumberWordConverter)->normalizeNumberWord($s); }
    private function wordsToNumber(string $s): int          { return (new \App\Services\Order\Parsing\NumberWordConverter)->wordsToNumber($s); }
}
