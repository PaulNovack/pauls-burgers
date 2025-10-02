<?php

namespace Tests\Unit\Order;

use Tests\TestCase;
use App\Services\Order\TextNormalizerImpl;
use App\Services\Order\Impl\DefaultModifierResolver;
use App\Services\Order\Parsing\{CommandParser, NumberWordConverter, NameMatcher};
use App\Services\Order\Dto\{AddById, AddByName, RemoveById, RemoveByName};
use Tests\Support\FakeMenuRepository;

final class CommandParserDataFileTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('rowsFromCsv')]
    public function test_from_file(
        string $file,          // absolute path to CSV
        int $line,             // exact CSV line number (1-based)
        string $utterance,
        ?int $expectId,
        ?string $expectName,
        int $expectQty,
        array $expectWith,
        array $expectWithout
    ): void {
        $menu   = new FakeMenuRepository(); // loads config/menu.php
        $norm   = new TextNormalizerImpl(null, new NumberWordConverter());
        $parser = new CommandParser(
            $norm,
            new NumberWordConverter(),
            new NameMatcher($menu, $norm),
            new DefaultModifierResolver(),
        );

        $ctx = sprintf('[%s:L%d] %s', basename($file), $line, $utterance);

        $out = $parser->parse($utterance);
        $this->assertNotNull($out, "$ctx failed to parse");

        $isRemove = (bool) preg_match('/^\s*(remove|delete|drop|minus|take\s+off)\b/i', $utterance);

        if ($isRemove) {
            if ($expectId !== null) {
                $this->assertInstanceOf(RemoveById::class, $out, "$ctx expected RemoveById");
                /** @var RemoveById $out */
                $this->assertSame($expectId, $out->id, "$ctx id mismatch");
            } else {
                $this->assertInstanceOf(RemoveByName::class, $out, "$ctx expected RemoveByName");
                /** @var RemoveByName $out */
                $this->assertSame(mb_strtolower((string)$expectName), mb_strtolower($out->name), "$ctx name mismatch");
            }
            $this->assertSame($expectQty, $out->qty, "$ctx qty mismatch");
            $this->assertEqualsCanonicalizing($expectWith, $out->needAdd,    "$ctx WITH mismatch");
            $this->assertEqualsCanonicalizing($expectWithout, $out->needRemove, "$ctx WITHOUT mismatch");
        } else {
            if ($expectId !== null) {
                $this->assertInstanceOf(AddById::class, $out, "$ctx expected AddById");
                /** @var AddById $out */
                $this->assertSame($expectId, $out->id, "$ctx id mismatch");
            } else {
                $this->assertInstanceOf(AddByName::class, $out, "$ctx expected AddByName");
                /** @var AddByName $out */
                $this->assertSame(mb_strtolower((string)$expectName), mb_strtolower($out->name), "$ctx name mismatch");
            }
            $this->assertSame($expectQty, $out->qty, "$ctx qty mismatch");
            $this->assertEqualsCanonicalizing($expectWith, $out->add,   "$ctx WITH mismatch");
            $this->assertEqualsCanonicalizing($expectWithout, $out->remove, "$ctx WITHOUT mismatch");
        }
    }

    public static function rowsFromCsv(): array
    {
        $file = self::locateCsv();
        return self::readCsvFile($file);
    }

    private static function locateCsv(): string
    {
        $env = $_SERVER['UTTERANCES_CSV'] ?? getenv('UTTERANCES_CSV');

        $candidates = array_values(array_filter([
            $env ?: null,
            \dirname(__DIR__) . '/fixtures/utterances.csv',     // tests/Unit -> tests/fixtures
            \dirname(__DIR__, 2) . '/fixtures/utterances.csv',  // <root>/tests/fixtures
            \getcwd() . '/tests/fixtures/utterances.csv',        // run from project root
        ]));

        foreach ($candidates as $c) {
            if (\is_file($c)) return $c;
        }
        throw new \RuntimeException('Could not find tests/fixtures/utterances.csv – set UTTERANCES_CSV or create the file.');
    }

    private static function readCsvFile(string $file): array
    {
        $rows = [];

        $fh = \fopen($file, 'r');
        if ($fh === false) throw new \RuntimeException("Unable to open CSV: {$file}");

        $header = \fgetcsv($fh) ?: [];
        $map    = \array_change_key_case(\array_flip($header), CASE_LOWER);
        $line   = 1; // header is line 1

        while (($row = \fgetcsv($fh)) !== false) {
            $line++; // current data line number
            if (!\count(\array_filter($row))) continue; // skip empty rows

            $utt   = (string)($row[$map['utterance']] ?? '');
            $qty   = (int)($row[$map['qty']] ?? 1);

            $idS   = \trim((string)($row[$map['id']] ?? ''));
            $id    = ($idS === '') ? null : (int)$idS;

            $nameS = \trim((string)($row[$map['name']] ?? ''));
            $name  = ($nameS === '') ? null : $nameS;

            $with     = self::parseList((string)($row[$map['with']] ?? ''));
            $without  = self::parseList((string)($row[$map['without']] ?? ''));

            if ($id !== null) $name = null; // id wins if both set

            // Name each dataset with filename + CSV line
            $label = sprintf('%s:L%d – %s', basename($file), $line, $utt);
            $rows[$label] = [$file, $line, $utt, $id, $name, $qty, $with, $without];
        }
        \fclose($fh);

        return $rows;
    }

    private static function parseList(string $s): array
    {
        $s = \trim($s);
        if ($s === '') return [];
        $parts = \preg_split('/\s*\|\s*|\s*,\s*/', $s) ?: [];
        $parts = \array_values(\array_filter(\array_map('trim', $parts), fn($x) => $x !== ''));
        // Title-case to match DefaultModifierResolver output
        return \array_map(fn($x) => \mb_convert_case($x, MB_CASE_TITLE, 'UTF-8'), $parts);
    }
}
