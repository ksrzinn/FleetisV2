<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;

class CreateClientFreightTableAction
{
    public function handle(Client $client, array $data): ClientFreightTable
    {
        return $client->freightTables()->create($data);
    }
}
