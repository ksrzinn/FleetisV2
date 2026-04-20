<?php
namespace App\Modules\Commercial\Actions;

use App\Modules\Commercial\Models\Client;

class CreateClientAction
{
    public function handle(array $data): Client
    {
        return Client::create($data);
    }
}
