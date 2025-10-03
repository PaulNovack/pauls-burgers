<?php
namespace App\Services\Order\Parsing;

use App\Services\Order\Contracts\ModifierResolver;
use App\Services\Order\Contracts\TextNormalizer;
use App\Services\Order\Dto\{ParsedCommand,AddById,AddByName,RemoveById,RemoveByName};

final class CommandParser
{
    public function __construct(
        private readonly TextNormalizer $N,
        private readonly NumberWordConverter $num,
        private readonly NameMatcher $nameMatcher,
        private readonly ModifierResolver $mods,
    ) {}

    /** Returns a ParsedCommand|"clear"|null */
    public function parse(string $text): ParsedCommand|string|null
    {
        $norm = $this->N->normalizeCommand($text);

        if ($this->isClear($norm)) return 'clear';

        // ADD by ID
        if (preg_match(
            '/^(?:add|added|and|also|plus|have\s+a|add\s+the|and\s+the|i\s+won|i\s+want|can\s+i\shave|could\s+you\s+get\s+me|give\s+me|include)\s+'.
            '(?:(?:at|in|on|to|for|please|me|us|the)\s+)*'.
            '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|'.
            'nine|ten|eleven|twelve)\s*,?\s+)?'.
            '(?:of\s+)?'.
            '(?:a|an)?\s*'.
            '(?:number|no\.|#)\s*'.               // ← now REQUIRED
            '(?:(?<id>\d+)|'.
            '(?<idw>(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|'.
            'nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|'.
            'eighteen|nineteen|twenty|thirty|forty|fifty|sixty)(?:[-\s]+'.
            '(?:one|two|three|four|five|six|seven|eight|nine))?)'.
            '(?:\'s|’s|s|es|ies)?' .
            ')\b'.
            '(?:\s*,\s*)?' .                      // allow comma after id
            '(?:\s+(?<size>small|regular|large))?'.
            '(?:.*?\bwith\b\s+(?<with>.*?))?'.
            '(?:.*?\bwithout\b\s+(?<without>.*))?'.
            '$/siu',
            $norm,
            $m
        )) {
            $qty  = $this->toQty($m['qty'] ?? '') ?: 1;
            $id   = !empty($m['id'])
                ? (int)$m['id']
                : $this->num->wordsToNumber($this->num->normalizeNumberWord($m['idw'] ?? ''));
            $adds = $this->mods->resolveList($this->splitList($m['with'] ?? ''));
            $rems = $this->mods->resolveList($this->splitList($m['without'] ?? ''));
            return new AddById($id,$qty,$adds,$rems);
        }

        // ADD by NAME
        if (preg_match(
            '/^(?:add|and|want|also|plus|i\s+want|have\s+a|give\s+me|i\s+would\s+like\s+some|can\s+i\s+have\s+some|can\s+i\s+have\s+a|include)\s+'
            . '(?:(?:at|in|on|to|for|please|me|us|the)\s+)*'
            . '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s+)?'
            . '(?:(?<size>small|regular|large)\s+)?'
            . '(?<name>.+?)(?=\s+(?:with|without)\b|$)'
            . '(?:\s+\bwith\b\s+(?<with>.*?))?'
            . '(?:\s+\bwithout\b\s+(?<without>.*))?'
            . '$/siu',
            $norm, $m
        )) {
            $qty  = $this->toQty($m['qty'] ?? '') ?: 1;
            $size = $this->N->normalizeSize($m['size'] ?? null);
            $name = trim($m['name'] ?? '');
            $adds = $this->mods->resolveList($this->splitList($m['with'] ?? ''));
            $rems = $this->mods->resolveList($this->splitList($m['without'] ?? ''));
            return new AddByName($name,$qty,$adds,$rems,$size);
        }

        // Fallback ADD (name only)
        if (preg_match(
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+'
            . '(?<name>.+?)'
            . '(?:\s+\bwith\b\s+(?<with>.*?))?'
            . '(?:\s+\bwithout\b\s+(?<without>.*))?'
            . '$/siu',$norm,$m
        )){
            $name = trim($m['name']??'');
            $adds = $this->mods->resolveList($this->splitList($m['with']??''));
            $rems = $this->mods->resolveList($this->splitList($m['without']??''));
            return new AddByName($name,1,$adds,$rems,null);
        }

        // REMOVE by ID
        if (preg_match(
            '/^(?:remove|we\'ve\s+moved\s+to|get\s+rid\s+of|forget|can\s+you\s+remove|could\s+you\s+remove|removing|take\s+back|cancel|delete|drop|minus|moving|takeoff|take\s+off|i\s*(?:do\s*not|don[’\']?t)\s+want)\s+'
            . '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s*,?\s+)?'
            . '(?:of\s+)?'
            . '(?:a|an|the)?\s*(?:number|no\.|#)?\s*'
            . '(?:(?<id>\d+)\s*(?:\'s|’s|s)?|(?<idw>(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty)(?:[-\s]+(?:one|two|three|four|five|six|seven|eight|nine))?)(?:\'s|’s|s|es|ies)?)\b'
            . '(?:\s+(?<size>small|regular|large))?'
            . '(?:.*?\bwith\b\s+(?<with>.*?))?'
            . '(?:.*?\bwithout\b\s+(?<without>.*))?'
            . '$/siu',
            $norm,$m
        )){
            $qty=$this->toQty($m['qty']??'')?:1;
            $size=$this->N->normalizeSize($m['size']??null);
            $id=!empty($m['id'])?(int)$m['id']:$this->num->wordsToNumber($this->num->normalizeNumberWord($m['idw']??''));
            $needAdd=$this->splitList($m['with']??'');
            $needRemove=$this->splitList($m['without']??'');
            return new RemoveById($id,$qty,$size,$needAdd,$needRemove);
        }

        // REMOVE by NAME
        if (preg_match(
            '/^(?:remove|We\'ve\s+moved\s+to|forget|get\s+rid\s+of|removing|take\s+back|cancel|cancell|delete|drop|minus|moving|takeoff|take\s+off|i\s*(?:do\s*not|don[’\']?t)\s+want)\s+'
            . '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s+)?'
            . '(?:(?<size>small|regular|large)\s+)?'
            . '(?<name>.+?)(?=\s+(?:with|without)\b|$)'
            . '(?:\s+\bwith\b\s+(?<with>.*?))?'
            . '(?:\s+\bwithout\b\s+(?<without>.*))?'
            . '$/siu',$norm,$m
        )){
            $qty=$this->toQty($m['qty']??'')?:1;
            $size=$this->N->normalizeSize($m['size']??null);
            $name=trim($m['name']??'');
            $needAdd=$this->splitList($m['with']??'');
            $needRemove=$this->splitList($m['without']??'');
            return new RemoveByName($name,$qty,$size,$needAdd,$needRemove);
        }

        return null;
    }

    private function isClear(string $s): bool
    {
        $lc = mb_strtolower(trim($s));
        return preg_match('/^\s*(clear|reset)\s*(list|order)?\s*[.?]?\s*$/u',$lc)
            || preg_match('/^\s*(delete|wipe|erase)\s+(list|order)\s*[.?]?\s*$/u',$lc)
            || preg_match('/^\s*(new|create new|start new)\s+(list|order)\s*[.?]?\s*$/u',$lc);
    }

    private function toQty(?string $s): int
    {
        if(!$s) return 0; $s=mb_strtolower(trim($s));
        if(ctype_digit($s)) return max(1,(int)$s);
        return ['one'=>1,'two'=>2,'to'=>2,'too'=>2,'three'=>3,'four'=>4,'for'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,'ten'=>10,'eleven'=>11,'twelve'=>12][$s] ?? 0;
    }

// app/Services/Order/Parsing/CommandParser.php

    /** split modifiers by commas/&/and and segment with a topping dictionary */
    /** Greedy, topping-aware list splitter (won't split "Thousand") */
    /** Split on commas and standalone "and/&", but never inside "Thousand Island". */
    /** Split toppings list, aware of multi-word toppings. */
    private function splitList(string $s): array
    {
        $s = trim($s);
        if ($s === '') return [];

        // Common ASR fixups that help recognition
        $s = preg_replace('/\branch\s+trusting\b/iu', 'ranch dressing', $s) ?? $s;

        // Protect "thousand island" so inner "and" isn't treated as a splitter
        $s = preg_replace('/\b(thousand)\s+(island)\b/iu', '$1_$2', $s) ?? $s;

        // Coarse split on commas or standalone "and"/"&"
        $chunks = preg_split('/\s*(?:,|\band\b|&)\s*/iu', $s) ?: [];

        // Greedy split within each chunk using known topping terms
        $known = $this->knownModTerms(); // lower-cased strings
        $out = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;

            // Restore placeholder
            $chunk = str_ireplace('thousand_island', 'thousand island', $chunk);

            // If the whole chunk is a known term, keep it as one
            $lc = mb_strtolower($chunk);
            if (in_array($lc, $known, true)) {
                $out[] = mb_convert_case($lc, MB_CASE_TITLE, 'UTF-8');
                continue;
            }

            // Greedy word scan (handles no punctuation, e.g. "ketchup mustard")
            $words = preg_split('/\s+/u', $lc) ?: [];
            $i = 0;
            while ($i < count($words)) {
                $best = null; $bestLen = 0;

                // Support up to 3-word toppings (e.g. thousand island dressing)
                for ($len = min(3, count($words) - $i); $len >= 1; $len--) {
                    $phrase = implode(' ', array_slice($words, $i, $len));
                    if (in_array($phrase, $known, true)) {
                        $best = $phrase; $bestLen = $len; break;
                    }
                }

                if ($best !== null) {
                    $out[] = mb_convert_case($best, MB_CASE_TITLE, 'UTF-8');
                    $i += $bestLen;
                } else {
                    // Skip common filler tokens
                    if (!preg_match('/^(with|without|of|a|an|the|some)$/u', $words[$i])) {
                        $out[] = mb_convert_case($words[$i], MB_CASE_TITLE, 'UTF-8');
                    }
                    $i++;
                }
            }
        }

        // Remove empties and dupes (order preserved)
        $seen = [];
        $out = array_values(array_filter($out, function ($x) use (&$seen) {
            $k = mb_strtolower($x);
            if ($k === '' || isset($seen[$k])) return false;
            $seen[$k] = true;
            return true;
        }));

        return $out;
    }

    /** Minimal dictionary used for greedy splitting (lower-cased). */
    private function knownModTerms(): array
    {
        static $terms;
        if ($terms !== null) return $terms;

        $canon = [
            'cheddar cheese','swiss cheese','american cheese','pepper jack',
            'blue cheese','bacon','onion','pickle','tomato','lettuce',
            'jalapeno','ketchup','mustard','mayo','bbq sauce',
            'ranch dressing','thousand island dressing','ice',
        ];

        $variants = [
            'cheddar','extra cheddar','swiss','american','pepperjack',
            'bleu cheese','bleu','onions','grilled onion','grilled onions',
            'pickles','tomatoes','jalapenos','jalapeño','jalapeños',
            'yellow mustard','mayonnaise','bbq','barbecue','barbeque',
            'ranch','thousand island',
        ];

        $terms = array_unique(array_map(
            fn($x) => mb_strtolower($x),
            array_merge($canon, $variants)
        ));

        return $terms;
    }

    /** Minimal dictionary used only for phrase boundary detection */
    private function modsDict(): array
    {
        static $d = null;
        if ($d !== null) return $d;

        $known = [
            // multi-word first
            'thousand island dressing', 'thousand island', 'ranch dressing',
            'blue cheese', 'bleu cheese', 'swiss cheese', 'cheddar cheese',
            'american cheese', 'pepper jack', 'grilled onions', 'bbq sauce',

            // single-word
            'ketchup', 'mustard', 'mayo', 'bbq', 'jalapeno', 'ice',
        ];

        $d = [];
        foreach ($known as $k) $d[mb_strtolower($k)] = true;
        return $d;
    }


    private function isKnownMod(string $p): bool
    {
        static $dict = null;
        if ($dict === null) {
            // Include multi-word items first so longest-match wins.
            $dict = array_fill_keys([
                'thousand island dressing',
                'thousand island',
                'ranch dressing',
                'blue cheese',
                'bleu cheese',
                'bbq sauce',
                'pepper jack',
                'cheddar cheese',
                'swiss cheese',
                'american cheese',
                'grilled onions',
                'jalapeno',
                'ketchup',
                'mustard',
                'mayo',
                'bbq',
                'ice',
            ], true);
        }
        return isset($dict[mb_strtolower(trim($p))]);
    }

    private function title(string $s): string
    {
        return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8');
    }


}
