<?php

namespace App\Shared;

class CsvSyntax
{
    // fgetcsv is deliberately permissive at EOF; validate RFC-style quoting first.
    public static function valid(string $input): bool
    {
        $state = 'start';
        $length = strlen($input);
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if ($state === 'quoted') {
                if ($char === '"') {
                    if ($i + 1 < $length && $input[$i + 1] === '"') {
                        $i++;
                    } else {
                        $state = 'end';
                    }
                }

                continue;
            }
            $separator = $char === ',' || $char === "\n" || $char === "\r";
            if ($state === 'end') {
                if (! $separator) {
                    return false;
                }$state = 'start';

                continue;
            }
            if ($char === '"') {
                if ($state !== 'start') {
                    return false;
                }$state = 'quoted';

                continue;
            }
            $state = $separator ? 'start' : 'unquoted';
        }

        return $state !== 'quoted';
    }
}
