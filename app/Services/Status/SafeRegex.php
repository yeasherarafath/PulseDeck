<?php

namespace App\Services\Status;

/**
 * Runs admin-supplied regexes against untrusted response bodies with a hard
 * backtracking cap (JIT off), so a pattern like (a+)+b cannot stall a worker.
 */
class SafeRegex
{
    private const BACKTRACK_LIMIT = '100000';

    public static function matches(string $subject, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        $previousLimit = ini_set('pcre.backtrack_limit', self::BACKTRACK_LIMIT);
        $previousJit = ini_set('pcre.jit', '0');

        try {
            if (@preg_match($pattern, '') === false) {
                $pattern = '/'.str_replace('/', '\/', $pattern).'/';
            }

            return @preg_match($pattern, $subject) === 1;
        } finally {
            if ($previousLimit !== false) {
                ini_set('pcre.backtrack_limit', $previousLimit);
            }

            if ($previousJit !== false) {
                ini_set('pcre.jit', $previousJit);
            }
        }
    }
}
