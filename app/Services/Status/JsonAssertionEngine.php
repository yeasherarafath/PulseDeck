<?php

namespace App\Services\Status;

use App\Enums\Status\AssertionOperator;

/**
 * Evaluates JSON-path assertions like $.status or $.data.items[0].status
 * against a decoded JSON response.
 */
class JsonAssertionEngine
{
    /**
     * @return array{found: bool, value: mixed}
     */
    public function getValue(mixed $data, string $path): array
    {
        $path = trim($path);

        if ($path === '' || $path === '$') {
            return ['found' => true, 'value' => $data];
        }

        $path = ltrim($path, '$');
        $tokens = $this->tokenize($path);

        if ($tokens === []) {
            return ['found' => false, 'value' => null];
        }

        $current = $data;

        foreach ($tokens as $token) {
            if (is_int($token)) {
                if (! is_array($current) || ! array_key_exists($token, $current)) {
                    return ['found' => false, 'value' => null];
                }

                $current = $current[$token];

                continue;
            }

            if (! is_array($current) || ! array_key_exists($token, $current)) {
                return ['found' => false, 'value' => null];
            }

            $current = $current[$token];
        }

        return ['found' => true, 'value' => $current];
    }

    public function evaluate(mixed $actual, bool $found, AssertionOperator $operator, mixed $expected): bool
    {
        return match ($operator) {
            AssertionOperator::Exists => $found,
            AssertionOperator::NotExists => ! $found,
            AssertionOperator::Equals => $found && $this->stringify($actual) === $this->stringify($expected),
            AssertionOperator::NotEquals => ! $found || $this->stringify($actual) !== $this->stringify($expected),
            AssertionOperator::Contains => $found && str_contains($this->stringify($actual), $this->stringify($expected)),
            AssertionOperator::NotContains => ! $found || ! str_contains($this->stringify($actual), $this->stringify($expected)),
            AssertionOperator::GreaterThan => $found && is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            AssertionOperator::LessThan => $found && is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            AssertionOperator::Matches => $found && $this->regexMatches($this->stringify($actual), $this->stringify($expected)),
        };
    }

    /**
     * @return list<int|string>
     */
    private function tokenize(string $path): array
    {
        $tokens = [];

        preg_match_all('/([^.\[\]]+)|\[(\d+)\]/', $path, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (isset($match[2]) && $match[2] !== '') {
                $tokens[] = (int) $match[2];
            } elseif (isset($match[1]) && $match[1] !== '') {
                $tokens[] = $match[1];
            }
        }

        return $tokens;
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '';
        }

        return (string) $value;
    }

    private function regexMatches(string $actual, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        if (@preg_match($pattern, '') === false) {
            $pattern = '/'.str_replace('/', '\/', $pattern).'/';
        }

        return @preg_match($pattern, $actual) === 1;
    }
}
