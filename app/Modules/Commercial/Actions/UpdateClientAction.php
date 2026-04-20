<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;

class UpdateClientAction
{
    public function handle(Client $client, array $data): Client
    {
        $client->update($data);
        return $client;
    }
}
