<?php

namespace Tests\Unit;

use App\Shared\CanonicalJson;
use App\Shared\Security\SensitiveData;
use PHPUnit\Framework\TestCase;

class CanonicalJsonTest extends TestCase
{
    public function test_object_order_and_mysql_json_whitespace_do_not_change_identity(): void
    {
        $this->assertSame(CanonicalJson::encode(['event' => 'x', 'value' => ['b' => 2, 'a' => 1]]), CanonicalJson::encode(json_decode('{"value": {"a": 1, "b": 2}, "event": "x"}', true)));
        $this->assertNotSame(CanonicalJson::encode([1, 2]), CanonicalJson::encode([2, 1]));
        $this->assertNotSame(CanonicalJson::encode(['value' => 1]), CanonicalJson::encode(['value' => '1']));
    }

    public function test_nested_sensitive_data_redaction_keeps_business_fields(): void
    {
        $this->assertSame(['id' => 'abc', 'nested' => [['passport' => '[REDACTED]', 'status' => 'ACTIVE']], 'api_secret' => '[REDACTED]'], SensitiveData::redact(['id' => 'abc', 'nested' => [['passport' => 'private', 'status' => 'ACTIVE']], 'api_secret' => 'hidden']));
    }
}
