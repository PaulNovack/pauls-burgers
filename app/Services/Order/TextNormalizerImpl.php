<?php

namespace App\Services\Order;

use App\Services\Order\Contracts\TextNormalizer;

final class TextNormalizerImpl implements TextNormalizer
{
    public function normalizeCommand(string $s): string
    {
        $s = trim($s ?? '');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = preg_replace('/[.!?]+$/u', '', $s) ?? $s;

        // --- remove conversational lead-ins (don’t inject "add" here to avoid "add add" duplicates)
        $s = preg_replace('/^\s*(?:yeah|yep|ok|okay|well|you\s*know|then|also|plus|and|just)\b[,:-]?\s*/iu', '', $s) ?? $s;

        // --- map request forms to "add "
        $s = preg_replace('/^\s*(?:i\s+want|i\'?d\s+like|i\s+would\s+like|i\'?ll\s+have|have\s+me|had\s+(?:a|an)|give\s+me|gimme|include|i\s+need|i\s+decided\s+i\s+want)\b[,:-]?\s*/iu','add ',$s) ?? $s;

        // "could|can|may|would you|y'all … give/get/add/bring me …"
        $s = preg_replace('/^\s*(?:can|could|may|would)\s+(?:you|ya?l{1,2}|y[\'’]?all)\s+(?:please\s+)?(?:give|get|add|bring)\s+me\b[,:-]?\s*/iu','add ',$s) ?? $s;

        // "y'all thinking/think you could add me …"
        $s = preg_replace('/^\s*(?:ya?l{1,2}|y[\'’]?all)\s+(?:think(?:ing)?\s+you\s+could)\s+add\s+me\b[,:-]?\s*/iu','add ',$s) ?? $s;

        // rare: people start with "at ..." (ASR), make it "add ..."
        $s = preg_replace('/^\s*at\b[,:-]?\s*/iu','add ',$s) ?? $s;

        // --- unify "with no X" / leading "no X" → "without X"
        $s = preg_replace('/\bwith\s+no\s+/iu','without ',$s) ?? $s;
        $s = preg_replace('/\bno\s+(?=[a-z])/iu','without ',$s) ?? $s;

        // --- quantity idioms
        // "a couple of ..." → "two ..."
        $s = preg_replace('/\b(?:a\s+)?couple\s+of\b/iu','two ',$s) ?? $s;

        // --- after "add ", strip early fillers repeatedly
        // e.g., add [please|me|us|the|a|an|some|like] ...
        $s = preg_replace('/^\s*add\s+(?:(?:in|on|to|for|please|me|us|the|a|an|some|like)\s+)+/iu','add ',$s) ?? $s;
        // e.g., add orders of ..., add a/one of (them|those) ...
        $s = preg_replace('/^\s*add\s+(?:orders?\s+of\s+)+/iu','add ',$s) ?? $s;
        $s = preg_replace('/^\s*add\s+(?:one\s+of\s+(?:them|those)\s+)+/iu','add ',$s) ?? $s;

        // --- number <words> → number <digits>
        $s = preg_replace_callback(
            '/\b(?:number|no\.|#)\s*('
            .'(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand)'
            .'(?:[-\s]+(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine))?'
            .')\b/iu',
            function ($m) {
                $phrase = $this->normalizeNumberWord($m[1]);
                $n = $this->wordsToNumber($phrase);
                return 'number '.$n;
            },
            $s
        ) ?? $s;

        // normalize "number 5s/fives" → "number 5"
        $s = preg_replace('/\b(?:number|no\.|#)\s*(\d+)\s*(?:\'s|’s|s|es)\b/iu','number $1',$s) ?? $s;

        // strip trailing ", I think"
        $s = preg_replace('/,\s*i\s*think\s*$/iu','',$s) ?? $s;

        // lexify (keep surface forms the tests expect)
        $s = $this->lexify($s);
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
        $s = preg_replace('/\bbarbe?cue\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bbar[-\s]*b[-\s]*q\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bb\.?\s*b\.?\s*q\.?\b/iu','bbq',$s) ?? $s;
        $s = preg_replace('/\bmilk[-\s]*shake\b/iu','milkshake',$s) ?? $s;
        $s = preg_replace('/\bjalapen(?:o|os)\b/iu','jalapeno',$s) ?? $s;
        $s = preg_replace('/\bmac\s*(?:and|&|n[\'’]?)\s*cheese\b/iu', 'mac & cheese', $s) ?? $s;
        // keep “coke” as-is
        $s = preg_replace('/\bcheeseburgers\b/iu','cheese burgers',$s) ?? $s;
        $s = preg_replace('/\bcheeseburger\b/iu','cheese burger',$s) ?? $s;
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
