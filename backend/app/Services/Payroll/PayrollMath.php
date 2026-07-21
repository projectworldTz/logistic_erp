<?php

namespace App\Services\Payroll;

/**
 * Decimal-safe money arithmetic for payroll calculations, backed by
 * bcmath rather than floats. Internal scale is 4 to absorb rounding from
 * percentage math; every result crossing back into a stored/returned
 * amount is rounded to 2dp via money().
 */
class PayrollMath
{
    private const SCALE = 4;

    public static function add(string|float|int $a, string|float|int $b): string
    {
        return bcadd((string) $a, (string) $b, self::SCALE);
    }

    public static function sub(string|float|int $a, string|float|int $b): string
    {
        return bcsub((string) $a, (string) $b, self::SCALE);
    }

    public static function mul(string|float|int $a, string|float|int $b): string
    {
        return bcmul((string) $a, (string) $b, self::SCALE);
    }

    public static function div(string|float|int $a, string|float|int $b): string
    {
        if (bccomp((string) $b, '0', self::SCALE) === 0) {
            return '0.0000';
        }

        return bcdiv((string) $a, (string) $b, self::SCALE);
    }

    /**
     * Percentage of a base amount, e.g. percent('500000', '10') = 10% of 500000.
     */
    public static function percent(string|float|int $base, string|float|int $rate): string
    {
        return self::div(self::mul((string) $base, (string) $rate), '100');
    }

    /**
     * Round to 2dp for storage/display, using bcmath (no float rounding).
     */
    public static function money(string|float|int $value): string
    {
        $negative = bccomp((string) $value, '0', self::SCALE) < 0;
        $abs = $negative ? bcmul((string) $value, '-1', self::SCALE) : (string) $value;
        $rounded = bcadd($abs, '0.005', self::SCALE);
        $truncated = bcadd($rounded, '0', 2);

        return $negative ? bcmul($truncated, '-1', 2) : $truncated;
    }

    public static function max(string|float|int $a, string|float|int $b): string
    {
        return bccomp((string) $a, (string) $b, self::SCALE) >= 0 ? (string) $a : (string) $b;
    }

    public static function min(string|float|int $a, string|float|int $b): string
    {
        return bccomp((string) $a, (string) $b, self::SCALE) <= 0 ? (string) $a : (string) $b;
    }

    public static function gt(string|float|int $a, string|float|int $b): bool
    {
        return bccomp((string) $a, (string) $b, self::SCALE) > 0;
    }
}
