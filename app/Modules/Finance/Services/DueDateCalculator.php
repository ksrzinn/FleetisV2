<?php

namespace App\Modules\Finance\Services;

use App\Modules\Commercial\Models\Client;
use Illuminate\Support\Carbon;

class DueDateCalculator
{
    public function compute(Client $client, Carbon $from): Carbon
    {
        return match ($client->payment_term_type) {
            'daily'      => $from->copy()->startOfDay(),
            'days_after' => $from->copy()->addDays((int) $client->payment_term_value),
            'monthly'    => $this->nextMonthlyDate($from, (int) $client->payment_term_value),
            'weekly'     => $this->nextWeeklyDate($from, (int) $client->payment_term_value),
            default      => $from->copy()->addDays(30),
        };
    }

    private function nextMonthlyDate(Carbon $from, int $day): Carbon
    {
        $day = min($day, 28);
        $candidate = $from->copy()->day($day);

        if ($candidate->lt($from->copy()->startOfDay())) {
            $candidate->addMonthNoOverflow();
        }

        return $candidate;
    }

    private function nextWeeklyDate(Carbon $from, int $isoDayOfWeek): Carbon
    {
        $candidate = $from->copy()->startOfDay();

        // isoWeekday(): 1=Mon … 7=Sun
        $diff = ($isoDayOfWeek - $candidate->isoWeekday() + 7) % 7;

        return $candidate->addDays($diff);
    }
}
