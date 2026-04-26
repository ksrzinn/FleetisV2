<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Carbon;
use Spatie\Holidays\Holidays;

class BusinessDayCalculator
{
    public function nextDate(Carbon $from, string $cadence, int $recurrenceDay): Carbon
    {
        $next = match ($cadence) {
            'weekly'   => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            'monthly'  => $from->copy()->addMonthNoOverflow()->setDay(min($recurrenceDay, 28)),
            'yearly'   => $from->copy()->addYear()->setDay(min($recurrenceDay, 28)),
            default    => $from->copy()->addMonth(),
        };

        return $this->adjustToBusinessDay($next);
    }

    public function adjustToBusinessDay(Carbon $date): Carbon
    {
        $holidays = $this->getHolidays($date->year);

        while ($date->isWeekend() || $this->isHoliday($date, $holidays)) {
            $date->addDay();
        }

        return $date;
    }

    private function isHoliday(Carbon $date, array $holidays): bool
    {
        return in_array($date->toDateString(), $holidays, true);
    }

    /** @return array<string> */
    private function getHolidays(int $year): array
    {
        // spatie/holidays: Holidays::for()->get($year) returns a Collection of
        // objects with a ->date Carbon property and ->name string property.
        return collect(Holidays::for(country: 'br', year: $year)->get())
            ->map(fn ($h) => $h['date']->toDateString())
            ->toArray();
    }
}
