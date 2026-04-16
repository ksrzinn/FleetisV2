<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_be_created_with_required_fields(): void
    {
        $company = Company::create([
            'name' => 'Transportadora Silva',
            'cnpj' => '12345678000190',
            'timezone' => 'America/Sao_Paulo',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('companies', ['name' => 'Transportadora Silva']);
        $this->assertSame('active', $company->status);
    }
}
