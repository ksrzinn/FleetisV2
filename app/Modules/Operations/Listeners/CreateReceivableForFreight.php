<?php

namespace App\Modules\Operations\Listeners;

use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Finance\Services\DueDateCalculator;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;

class CreateReceivableForFreight
{
    private DueDateCalculator $calculator;

    public function __construct(?DueDateCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new DueDateCalculator();
    }

    public function handle(FreightEnteredAwaitingPayment $event): void
    {
        $freight = $event->freight;

        if ($freight->freight_value === null) {
            throw new \InvalidArgumentException(
                "Cannot create receivable: freight #{$freight->id} has no freight_value."
            );
        }

        $client = Client::withoutGlobalScopes()->find($freight->client_id);
        $dueDate = $this->calculator->compute($client, now());

        Receivable::create([
            'company_id'  => $freight->company_id,
            'client_id'   => $freight->client_id,
            'freight_id'  => $freight->id,
            'amount_due'  => $freight->freight_value,
            'amount_paid' => '0.00',
            'due_date'    => $dueDate->toDateString(),
            'status'      => 'open',
        ]);
    }
}
