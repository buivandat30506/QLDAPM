<?php

namespace App\Shared\Security;

use Illuminate\Contracts\Support\Arrayable;

class SensitiveData
{
    public static function redact(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif (is_object($value)) {
            $value = (array) $value;
        }
        if (! is_array($value)) {
            if (is_string($value) && preg_match('/^\s*[\[{]/', $value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return json_encode(self::redact($decoded), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
            }
            return $value;
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (preg_match('/password|secret|token|passport|id_card|email|phone|full_name|authorization|cookie/', strtolower((string) $key))) {
                $result[$key] = '[REDACTED]';
            } else {
                $result[$key] = self::redact($item);
            }
        }

        return $result;
    }
}
