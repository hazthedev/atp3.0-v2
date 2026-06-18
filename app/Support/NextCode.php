<?php

namespace App\Support;

/**
 * Sequential code generator. Scans ONLY strictly-numeric <prefix><digits> codes for the
 * true numeric max (so seeded non-numeric codes can't drop the sequence back to a
 * colliding value — the #394 bug). Callers create inside a transaction and retry on a
 * unique violation; the unique('code') constraint is the real race guard (H7).
 */
class NextCode
{
    public static function sequential(string $modelClass, string $prefix, int $pad, int $first = 1): string
    {
        $max = $modelClass::query()
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(fn ($code) => self::numericSuffix((string) $code, $prefix))
            ->filter(fn ($n) => $n !== null)
            ->max();

        $next = $max === null ? $first : $max + 1;

        return $prefix.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    private static function numericSuffix(string $code, string $prefix): ?int
    {
        if (! str_starts_with($code, $prefix)) {
            return null;
        }
        $suffix = substr($code, strlen($prefix));

        return $suffix !== '' && ctype_digit($suffix) ? (int) $suffix : null;
    }
}
