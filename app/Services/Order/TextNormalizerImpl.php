<?php

namespace App\Services\Order;

use App\Services\Order\Contracts\TextNormalizer;

final class TextNormalizerImpl implements TextNormalizer
{
    public function normalizeCommand(string $s): string
    {
        $s = trim($s ?? '');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $this->safeLog('regex #1', [$s]);

        // strip trailing punctuation
        $s = preg_replace('/[.!?]+$/u', '', $s) ?? $s;
        $this->safeLog('regex #2', [$s]);

        // Fix "and at ..." quirk -> "add ..."
        $s = preg_replace('/^\s*(?:and\s+at)\b[,:-]?\s*/iu', 'add ', $s) ?? $s;
        $this->safeLog('regex #3', [$s]);

        // Drop preambles at the very start (greetings/softeners/y'all/could you/etc.)
        $s = preg_replace(
            '/^\s*(?:'
            .'hey|hi|hello|please|ok|okay|alright|anyway|so|well|you\s+know'
            .'|(?:could|would|can|will)\s+you'
            .'|(?:could|would|can|will)\s+y[\'’]?all'
            .'|y[\'’]?all(?:\s+(?:think(?:ing)?\s+)?you\s+could)?'
            .')\b[,:-]?\s*/iu',
            '',
            $s
        ) ?? $s;

        // Map variant starts to "add ..." (incl. ASR 'had'/'I had')
        $s = preg_replace(
            '/^\s*(?:'
            .'and|also|plus|yeah|yep|uh|um|then|at|just'
            .'|i\s+want|i\'?d\s+like|i\s+would\s+like|i\'?ll\s+have|i\s+need'
            .'|i\s+decided\s+i\s+want'
            .'|give\s+me|gimme|include'
            .'|have(?:\s+(?:me|us|a|an|some))?'
            .'|(?:could|can|may)\s+i\s+(?:have|get)'
            .'|had|i\s+had'
            .')\b[,:-]?\s*/iu',
            'add ',
            $s
        ) ?? $s;
        $this->safeLog('regex #4', [$s]);

        // --- Apply qty/phrase mappers BEFORE stripping fillers ---

        // "a/one couple of" => "two"
        $s = preg_replace('/\b(?:a|one)?\s*couple\s+of\b/iu', 'two ', $s) ?? $s;

        // Allow "orders of ..." phrasing
        $s = preg_replace('/\borders?\s+of\b/iu', '', $s) ?? $s;

        // Collapse "of them/the/these/those"
        $s = preg_replace('/\bof\s+(?:the|them|those|these)\b/iu', '', $s) ?? $s;

        // Treat "lettuce wrap <name>" as just "<name>"
        $s = preg_replace('/\blettuce\s+wrap\s+/iu', '', $s) ?? $s;

        // --------------------------------------------------------

        // Remove filler words right after "add"
        // (NOTE: do NOT include "a couple of|couple of" here or you’ll erase the qty)
        $s = preg_replace(
            '/^\s*add\s+(?:(?:'
            .'add|and|' // remove duplicate "add" and a leading "and"
            .'in|on|to|for|please|me|us|the|a|an|have|just|like|kinda|kind\s+of|sort\s+of|'
            .'some|some\s+of|a\s+few|few'
            .')\s+)+/iu',
            'add ',
            $s
        ) ?? $s;
        $this->safeLog('regex #5', [$s]);

        // "with no <modifier>" → "without <modifier>" (prevents "with without ...")
        $s = preg_replace('/\bwith\s+no\s+/iu', 'without ', $s) ?? $s;

        // Standalone "no <modifier>" → "without <modifier>", but NOT after "with", and not "no. 7"
        $s = preg_replace('/(?<!\bwith\s)\bno(?!\.)\s+/iu', 'without ', $s) ?? $s;

        // ASR quirk: "add to/too" -> "add two"
        $s = preg_replace('/^\s*add\s+(?:to|too)\b/iu', 'add two ', $s) ?? $s;
        $this->safeLog('regex #6', [$s]);

        // Convert "number <number-words>" -> "number <digits>"
        $s = preg_replace_callback(
            '/\b(?:number|no\.|#)\s*('
            .'(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|'
            .'thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|'
            .'twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|'
            .'hundred|thousand)'
            .'(?:[-\s]+'
            .'(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|'
            .'thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|'
            .'twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|'
            .'hundred|thousand)'
            .')*'
            .')\b/iu',
            function ($m) {
                $phrase = $this->normalizeNumberWord($m[1]);
                $n = $this->wordsToNumber($phrase);
                return 'number ' . $n;
            },
            $s
        ) ?? $s;
        $this->safeLog('regex #7', [$s]);

        // Tidy "number 16's" / "number 3s" -> "number 16" / "number 3"
        $s = preg_replace('/\b(?:number|no\.|#)\s*(\d+)\s*(?:\'s|’s|s|es)\b/iu', 'number $1', $s) ?? $s;
        $this->safeLog('regex #8', [$s]);

        // Strip trailing hedges like ", I think" / "I guess" / "maybe"
        $s = preg_replace('/\s*,?\s*(?:i\s+think|i\s+guess|i\s+suppose|maybe)\s*$/iu', '', $s) ?? $s;

        // IMPORTANT: keep lexify out of normalizeCommand; we do it in normName() for matching.
        return $s;
    }


    public function normalizeSize(?string $size): ?string
    {
        if (!$size) return null;
        $s = mb_strtolower(trim($size));
        return match ($s) {
            'small'   => 'Small',
            'regular' => 'Regular',
            'large'   => 'Large',
            default   => null,
        };
    }

    public function normName(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = $this->stripDiacritics($s);
        $s = $this->lexify($s);                     // apply synonyms here (NOT in normalizeCommand)
        $s = str_replace('-', ' ', $s);
        $s = preg_replace('/[^\p{L}\p{N}& ]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        // singularize tokens so "fries" ~ "fry", "rings" ~ "ring"
        $tokens = array_filter(explode(' ', $s));
        $tokens = array_map([$this, 'singularize'], $tokens);
        return implode(' ', $tokens);
    }

    public function stripDiacritics(string $s): string
    {
        $from = ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ','’','ʼ','ʻ','ˈ'];
        $to   = ['a','e','i','o','u','n','a','e','i','o','u','n',"'", "'", "'", "'"];
        return str_replace($from, $to, $s);
    }

    private function singularize(string $w): string
    {
        if (preg_match('/(.*[^aeiou])ies$/u', $w, $m)) return $m[1].'y'; // fries -> fry
        if (preg_match('/(.*)es$/u', $w, $m)) return $m[1];              // tomatoes -> tomato (simple)
        if (preg_match('/(.*)s$/u',  $w, $m)) return $m[1];              // rings -> ring
        return $w;
    }

    private function lexify(string $s): string
    {
        // BBQ variants
        $s = preg_replace('/\bbarbe?cue\b/iu', 'bbq', $s) ?? $s;
        $s = preg_replace('/\bbar[-\s]*b[-\s]*q\b/iu', 'bbq', $s) ?? $s;
        $s = preg_replace('/\bb\.?\s*b\.?\s*q\.?\b/iu', 'bbq', $s) ?? $s;

        // Milkshake
        $s = preg_replace('/\bmilk[-\s]*shake\b/iu', 'milkshake', $s) ?? $s;

        // Mac & Cheese
        $s = preg_replace('/\bmac\s*(?:and|&|n[\'’]?)\s*cheese\b/iu', 'mac & cheese', $s) ?? $s;

        // Jalapeño normalize
        $s = preg_replace('/\bjalapen(?:o|os)\b/iu', 'jalapeno', $s) ?? $s;

        // Coke → Coca-Cola (only at match-time, not in raw command)
        $s = preg_replace('/\bcoke\b/iu', 'coca cola', $s) ?? $s;

        // Cheeseburger → "cheese burger" to help "blue cheese burger" vs "cheeseburger"
        $s = preg_replace('/\bcheeseburgers\b/iu', 'cheese burgers', $s) ?? $s;
        $s = preg_replace('/\bcheeseburger\b/iu', 'cheese burger', $s) ?? $s;

        return $s;
    }

    // --- number-word helpers (reuse logic without DI) ---

    private function normalizeNumberWord(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['–','—','-'], ' ', $s);
        $s = preg_replace('/[^\p{L}\p{N} ]+/u', '', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';

        // common ASR homophones for qty/ids
        $s = preg_replace('/\bto\b/u',  'two',  $s) ?? $s;
        $s = preg_replace('/\btoo\b/u', 'two',  $s) ?? $s;
        $s = preg_replace('/\bfor\b/u', 'four', $s) ?? $s;

        // singularize LAST token only
        if ($s !== '') {
            $tokens = preg_split('/\s+/u', $s) ?: [];
            if ($tokens) {
                $i = count($tokens)-1;
                $last = $tokens[$i];
                $last = preg_replace('/\'s$/u', '', $last) ?? $last;
                if (preg_match('/(.*[^aeiou])ies$/u', $last, $m)) {
                    $last = $m[1].'y';
                } elseif (preg_match('/(.*)s$/u', $last, $m)) {
                    $last = $m[1];
                }
                $tokens[$i] = $last;
                $s = implode(' ', $tokens);
            }
        }
        return $s;
    }

    private function wordsToNumber(string $s): int
    {
        $s = trim($s);
        if ($s === '') return 0;
        $s = mb_strtolower($s);

        $units = [
            'zero'=>0,'one'=>1,'two'=>2,'to'=>2,'too'=>2,'three'=>3,'four'=>4,'for'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,
            'ten'=>10,'eleven'=>11,'twelve'=>12,'thirteen'=>13,'fourteen'=>14,'fifteen'=>15,'sixteen'=>16,'seventeen'=>17,'eighteen'=>18,'nineteen'=>19,
        ];
        $tens = ['twenty'=>20,'thirty'=>30,'forty'=>40,'fifty'=>50,'sixty'=>60,'seventy'=>70,'eighty'=>80,'ninety'=>90];

        $total = 0; $current = 0;
        $tokens = preg_split('/\s+/u', $s) ?: [];
        foreach ($tokens as $w) {
            if (isset($units[$w])) { $current += $units[$w]; continue; }
            if (isset($tens[$w]))  { $current += $tens[$w];  continue; }
            if ($w === 'hundred')  { if ($current === 0) $current = 1; $current *= 100; continue; }
            if ($w === 'thousand') { if ($current === 0) $current = 1; $total += $current * 1000; $current = 0; continue; }
        }
        return $total + $current;
    }

    /** No-op when facades aren't booted (e.g., plain PHPUnit) */
    private function safeLog(string $msg, array $context = []): void
    {
        try {
            \Illuminate\Support\Facades\Log::info($msg, $context);
        } catch (\Throwable $e) {
            // swallow when Facade root isn't set
        }
    }
}
