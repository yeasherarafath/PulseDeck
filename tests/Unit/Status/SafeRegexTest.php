<?php

namespace Tests\Unit\Status;

use App\Services\Status\SafeRegex;
use PHPUnit\Framework\TestCase;

class SafeRegexTest extends TestCase
{
    public function test_plain_and_delimited_patterns_match(): void
    {
        $this->assertTrue(SafeRegex::matches('hello world', '/wor.d/'));
        $this->assertTrue(SafeRegex::matches('a/b', 'a/b'));
        $this->assertFalse(SafeRegex::matches('abc', '/xyz/'));
        $this->assertFalse(SafeRegex::matches('abc', ''));
    }

    public function test_catastrophic_pattern_is_cut_off_quickly(): void
    {
        $started = microtime(true);

        $this->assertFalse(SafeRegex::matches(str_repeat('a', 5000).'!', '/(a+)+b/'));
        $this->assertLessThan(3.0, microtime(true) - $started);
    }

    public function test_ini_settings_are_restored(): void
    {
        $limit = ini_get('pcre.backtrack_limit');

        SafeRegex::matches('x', '/x/');

        $this->assertSame($limit, ini_get('pcre.backtrack_limit'));
    }
}
