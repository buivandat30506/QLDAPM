<?php

namespace Tests\Unit;

use App\Services\Core\MasterDataTransfer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvSafetyTest extends TestCase
{
    public static function dangerousCells(): array
    {
        return [['=1+1'], ["\t=HYPERLINK(\"example\")"], ['  +CMD'], ['-1+2'], ['@SUM(A1)'], ["\u{FEFF}=1+1"]];
    }

    #[DataProvider('dangerousCells')]
    public function test_formula_prefixes_are_neutralized_even_after_whitespace_or_bom(string $input): void
    {
        $this->assertSame("'".$input, MasterDataTransfer::safeCell($input));
        $this->assertSame('Tên thông thường', MasterDataTransfer::safeCell('Tên thông thường'));
    }
}
