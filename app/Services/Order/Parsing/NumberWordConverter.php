<?php
namespace App\Services\Order\Parsing;

final class NumberWordConverter
{
    public function normalizeNumberWord(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['–','—','-'], ' ', $s);
        $s = preg_replace('/[^\p{L}\p{N} ]+/u','',$s) ?? '';
        $s = preg_replace('/\s+/u',' ',$s) ?? '';
        $s = preg_replace('/\bto\b/u','two',$s) ?? $s;
        $s = preg_replace('/\btoo\b/u','two',$s) ?? $s;
        $s = preg_replace('/\bfor\b/u','four',$s) ?? $s;

        if ($s !== '') {
            $t = preg_split('/\s+/u', $s) ?: [];
            if ($t) {
                $i = count($t) - 1;
                $last = preg_replace("/'s$/u", '', $t[$i]) ?? $t[$i];
                if (preg_match('/(.*[^aeiou])ies$/u', $last, $m)) $last = $m[1] . 'y';
                elseif (preg_match('/(.*)s$/u', $last, $m))      $last = $m[1];
                $t[$i] = $last;
                $s = implode(' ', $t);
            }
        }
        return $s;
    }

    public function wordsToNumber(string $s): int
    {
        $s = mb_strtolower(trim($s));
        if ($s === '') return 0;

        $units = [
            'zero'=>0,'one'=>1,'two'=>2,'to'=>2,'too'=>2,'three'=>3,'four'=>4,'for'=>4,'five'=>5,
            'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,'ten'=>10,'eleven'=>11,'twelve'=>12,'thirteen'=>13,
            'fourteen'=>14,'fifteen'=>15,'sixteen'=>16,'seventeen'=>17,'eighteen'=>18,'nineteen'=>19,
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
}
