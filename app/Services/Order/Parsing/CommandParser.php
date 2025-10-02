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
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+'
            . '(?:(?:at|in|on|to|for|please|me|us|the)\s+)*'
            . '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s*,?\s+)?'
            . '(?:of\s+)?'
            . '(?:a|an)?\s*(?:number|no\.|#)?\s*'
            . '(?:(?<id>\d+)\s*(?:\'s|’s|s)?|(?<idw>(?:zero|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty)(?:[-\s]+(?:one|two|three|four|five|six|seven|eight|nine))?)(?:\'s|’s|s|es|ies)?)\b'
            . '(?:\s+(?<size>small|regular|large))?'
            . '(?:.*?\bwith\b\s+(?<with>.*?))?'
            . '(?:.*?\bwithout\b\s+(?<without>.*))?'
            . '$/siu',
            $norm, $m
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
            '/^(?:add|and|also|plus|i\s+want|give\s+me|include)\s+'
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
            '/^(?:remove|delete|drop|minus|take\s+off)\s+'
            . '(?:(?<qty>\d+|one|two|to|too|three|four|for|five|six|seven|eight|nine|ten|eleven|twelve)\s*,?\s+)?'
            . '(?:of\s+)?'
            . '(?:a|an)?\s*(?:number|no\.|#)?\s*'
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
            '/^(?:remove|delete|drop|minus|take\s+off)\s+'
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

    private function splitList(string $s): array
    {
        $s=trim($s); if($s==='') return [];
        $parts=preg_split('/\s*(?:,|and|&)\s*/iu',$s)?:[];
        $parts=array_map(fn($p)=>mb_convert_case(trim($p), MB_CASE_TITLE, 'UTF-8'),$parts);
        return array_values(array_filter($parts,fn($p)=>$p!==''));
    }
}
