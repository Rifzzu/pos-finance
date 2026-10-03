<?php

namespace App\Support;

/**
 * Uang sebagai string desimal. JANGAN float. Semua operasi via BCMath.
 */
final class Money
{
    public static function normalize(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('Nilai uang tidak valid.');
        }

        return self::round((string) $value, 2);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, 2);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, 2);
    }

    /** Kalikan lalu bulatkan ke 2 desimal (half-up). */
    public static function mul(string $a, string $b): string
    {
        return self::round(bcmul($a, $b, 6), 2);
    }

    /** Pembulatan half-up ke $scale desimal (default 2). */
    public static function round(string $value, int $scale = 2): string
    {
        $value = trim($value);
        $negative = str_starts_with($value, '-');
        $abs = ltrim($value, '-');

        if (! is_numeric($abs)) {
            throw new \InvalidArgumentException('Nilai uang tidak valid.');
        }

        $factor = bcpow('10', (string) $scale);
        $shifted = bcmul($abs, $factor, 5);
        $rounded = bcadd($shifted, '0.5', 0);
        $result = bcdiv($rounded, $factor, $scale);

        if ($negative && bccomp($rounded, '0', 0) !== 0) {
            return '-'.$result;
        }

        return $result;
    }

    /** -1 | 0 | 1 */
    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, 2);
    }
}
