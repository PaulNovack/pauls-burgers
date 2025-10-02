<?php
namespace App\Services\Order\Support;


final class Strings
{
    public static function title(string $s): string { return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8'); }
}
