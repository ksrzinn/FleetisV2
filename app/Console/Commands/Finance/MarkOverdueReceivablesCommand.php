<?php

namespace App\Console\Commands\Finance;

use App\Modules\Finance\Models\Receivable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkOverdueReceivablesCommand extends Command
{
    protected $signature = 'finance:mark-overdue-receivables';

    protected $description = 'Mark open and partially_paid receivables as overdue when their due date has passed';

    public function handle(): int
    {
        // Bypass RLS: this command operates across all tenants
        DB::statement("SET LOCAL app.current_company_id = ''");

        $count = Receivable::whereIn('status', ['open', 'partially_paid'])
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);

        $this->info("Marked {$count} receivable(s) as overdue.");

        return self::SUCCESS;
    }
}
