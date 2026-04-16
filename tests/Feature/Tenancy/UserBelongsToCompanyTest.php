<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBelongsToCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_a_company(): void
    {
        $company = Company::create([
            'name' => 'Acme',
            'cnpj' => '11111111000111',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->assertInstanceOf(Company::class, $user->company);
        $this->assertSame($company->id, $user->company->id);
    }
}
