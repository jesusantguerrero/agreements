<?php

namespace Insane\Agreements\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Billing cycles and how to step from one billing date to the next. */
final class Cycle
{
    public const MONTHLY = 'monthly';
    public const BIWEEKLY = 'biweekly';
    public const WEEKLY = 'weekly';
    public const QUARTERLY = 'quarterly';
    public const YEARLY = 'yearly';
    public const ONE_TIME = 'one_time';

    public const ALL = [self::MONTHLY, self::BIWEEKLY, self::WEEKLY, self::QUARTERLY, self::YEARLY, self::ONE_TIME];

    public const LABELS = [
        self::MONTHLY => 'Mensual',
        self::BIWEEKLY => 'Quincenal',
        self::WEEKLY => 'Semanal',
        self::QUARTERLY => 'Trimestral',
        self::YEARLY => 'Anual',
        self::ONE_TIME => 'Pago único',
    ];

    public static function monthsIn(string $cycle): ?int
    {
        return [self::MONTHLY => 1, self::QUARTERLY => 3, self::YEARLY => 12][$cycle] ?? null;
    }

    /** $day of the month of $date, clamped to the month length (31 → 30 in April). */
    public static function dayInMonth(CarbonInterface $date, int $day): Carbon
    {
        $month = Carbon::parse($date)->startOfMonth();

        return $month->day(min($day, $month->daysInMonth));
    }

    /** First billing date on/after $start. */
    public static function first(string $cycle, CarbonInterface $start, ?int $invoiceDay = null): Carbon
    {
        $start = Carbon::parse($start)->startOfDay();
        if (! $invoiceDay || ! self::monthsIn($cycle)) {
            return $start;
        }
        $candidate = self::dayInMonth($start, $invoiceDay);

        return $candidate->lt($start) ? self::dayInMonth($start->copy()->addMonthNoOverflow(), $invoiceDay) : $candidate;
    }

    /** Billing date after $date, or null for one-time contracts. */
    public static function next(string $cycle, CarbonInterface $date, ?int $invoiceDay = null): ?Carbon
    {
        $date = Carbon::parse($date)->startOfDay();

        if ($months = self::monthsIn($cycle)) {
            $nextMonth = $date->copy()->startOfMonth()->addMonthsNoOverflow($months);

            return self::dayInMonth($nextMonth, $invoiceDay ?? $date->day);
        }

        return match ($cycle) {
            self::WEEKLY => $date->copy()->addWeek(),
            self::BIWEEKLY => $date->copy()->addWeeks(2),
            default => null,
        };
    }

    /** Human label of the period that starts on $date ("octubre 2026", "1–14 oct 2026"). */
    public static function periodLabel(string $cycle, CarbonInterface $date): string
    {
        $date = Carbon::parse($date)->locale('es');

        return match ($cycle) {
            self::MONTHLY => $date->translatedFormat('F Y'),
            self::QUARTERLY => $date->translatedFormat('M Y') . ' – ' . $date->copy()->addMonthsNoOverflow(2)->translatedFormat('M Y'),
            self::YEARLY => $date->translatedFormat('Y'),
            self::WEEKLY => $date->translatedFormat('j M') . ' – ' . $date->copy()->addDays(6)->translatedFormat('j M Y'),
            self::BIWEEKLY => $date->translatedFormat('j M') . ' – ' . $date->copy()->addDays(13)->translatedFormat('j M Y'),
            default => $date->translatedFormat('j M Y'),
        };
    }
}
