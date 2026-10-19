<?php

namespace Tests\Unit;

use App\Shared\CsvSyntax;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvSyntaxTest extends TestCase
{
    public static function examples(): array
    {
        return [["a,b,c\r\n1,2,3\r\n", true], ["a,\"b\nline\",\"c\"\"d\"\n", true],
            ['a,"not closed', false], ['a,b"unexpected,c', false], ['a,"closed"trailing,c', false], ['a,,,', true]];
    }

    #[DataProvider('examples')]
    public function test_csv_quoting_rejects_incomplete_files_and_preserves_escaped_or_multiline_fields(string $input, bool $valid): void
    {
        $this->assertSame($valid, CsvSyntax::valid($input));
    }
}
