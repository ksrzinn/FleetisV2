<?php

namespace Tests\Feature\Finance;

use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Services\DueDateCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DueDateCalculatorTest extends TestCase
{
    private DueDateCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DueDateCalculator();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    // ── daily ────────────────────────────────────────────────────────────────

    public function test_daily_term_returns_the_from_date(): void
    {
        Carbon::setTestNow('2026-04-15');
        $client = new Client(['payment_term_type' => 'daily', 'payment_term_value' => null]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-15', $result->toDateString());
    }

    // ── days_after ───────────────────────────────────────────────────────────

    public function test_days_after_adds_n_days_to_from_date(): void
    {
        Carbon::setTestNow('2026-04-15');
        $client = new Client(['payment_term_type' => 'days_after', 'payment_term_value' => 30]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-05-15', $result->toDateString());
    }

    public function test_days_after_with_1_day_returns_tomorrow(): void
    {
        Carbon::setTestNow('2026-04-15');
        $client = new Client(['payment_term_type' => 'days_after', 'payment_term_value' => 1]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-16', $result->toDateString());
    }

    // ── null / unconfigured ──────────────────────────────────────────────────

    public function test_null_term_type_defaults_to_30_days(): void
    {
        Carbon::setTestNow('2026-04-15');
        $client = new Client(['payment_term_type' => null, 'payment_term_value' => null]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-05-15', $result->toDateString());
    }

    // ── monthly ──────────────────────────────────────────────────────────────

    public function test_monthly_returns_target_day_in_current_month_when_not_yet_passed(): void
    {
        Carbon::setTestNow('2026-04-10'); // today day=10, target day=15 → still in April
        $client = new Client(['payment_term_type' => 'monthly', 'payment_term_value' => 15]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-15', $result->toDateString());
    }

    public function test_monthly_returns_target_day_when_today_matches(): void
    {
        Carbon::setTestNow('2026-04-15'); // today IS day 15
        $client = new Client(['payment_term_type' => 'monthly', 'payment_term_value' => 15]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-15', $result->toDateString());
    }

    public function test_monthly_rolls_to_next_month_when_target_day_has_passed(): void
    {
        Carbon::setTestNow('2026-04-20'); // today day=20, target day=15 → already passed → May
        $client = new Client(['payment_term_type' => 'monthly', 'payment_term_value' => 15]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-05-15', $result->toDateString());
    }

    public function test_monthly_caps_day_at_28_to_avoid_month_end_issues(): void
    {
        Carbon::setTestNow('2026-01-10'); // target day=31 → capped to 28
        $client = new Client(['payment_term_type' => 'monthly', 'payment_term_value' => 31]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-01-28', $result->toDateString());
    }

    public function test_monthly_with_capped_day_rolls_to_next_month_when_past_28(): void
    {
        Carbon::setTestNow('2026-01-29'); // day=29, target=31 capped to 28 → already passed → Feb 28
        $client = new Client(['payment_term_type' => 'monthly', 'payment_term_value' => 31]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-02-28', $result->toDateString());
    }

    // ── weekly (ISO weekday: 1=Mon … 7=Sun) ─────────────────────────────────

    public function test_weekly_returns_today_when_today_is_the_target_weekday(): void
    {
        Carbon::setTestNow('2026-04-20'); // Monday = ISO 1
        $client = new Client(['payment_term_type' => 'weekly', 'payment_term_value' => 1]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-20', $result->toDateString());
    }

    public function test_weekly_returns_next_occurrence_when_weekday_has_passed_this_week(): void
    {
        Carbon::setTestNow('2026-04-21'); // Tuesday = ISO 2, target Monday = ISO 1
        $client = new Client(['payment_term_type' => 'weekly', 'payment_term_value' => 1]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-27', $result->toDateString()); // next Monday
    }

    public function test_weekly_returns_next_day_when_target_is_the_following_day(): void
    {
        Carbon::setTestNow('2026-04-26'); // Sunday = ISO 7, target Monday = ISO 1
        $client = new Client(['payment_term_type' => 'weekly', 'payment_term_value' => 1]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-27', $result->toDateString()); // tomorrow is Monday
    }

    public function test_weekly_handles_end_of_week_to_start_of_next(): void
    {
        Carbon::setTestNow('2026-04-24'); // Friday = ISO 5, target Sunday = ISO 7
        $client = new Client(['payment_term_type' => 'weekly', 'payment_term_value' => 7]);

        $result = $this->calculator->compute($client, Carbon::now());

        $this->assertEquals('2026-04-26', $result->toDateString()); // this Sunday
    }
}
