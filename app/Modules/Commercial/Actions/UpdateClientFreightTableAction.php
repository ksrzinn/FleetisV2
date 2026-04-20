<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\ClientFreightTable;

class UpdateClientFreightTableAction
{
    public function handle(ClientFreightTable $table, array $data): ClientFreightTable
    {
        $table->update($data);
        return $table;
    }
}
