<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_creates_company_user_and_roles_atomically(): void
    {
        $response = $this->post('/register', [
            'company_name' => 'Transportadora X',
            'cnpj' => '33333333000133',
            'name' => 'Alice Admin',
            'email' => 'alice@x.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $response->assertRedirect('/dashboard');

        $company = Company::where('cnpj', '33333333000133')->first();
        $this->assertNotNull($company);

        $user = User::where('email', 'alice@x.test')->first();
        $this->assertSame($company->id, $user->company_id);

        $this->assertSame(3, Role::where('company_id', $company->id)->count());
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $this->assertTrue($user->hasRole('Admin'));
    }

    public function test_signup_rolls_back_on_failure(): void
    {
        Company::create(['name' => 'Pre', 'cnpj' => '44444444000144', 'timezone' => 'UTC', 'status' => 'active']);

        $response = $this->post('/register', [
            'company_name' => 'Transportadora Y',
            'cnpj' => '44444444000144',
            'name' => 'Bob',
            'email' => 'bob@y.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $response->assertSessionHasErrors('cnpj');
        $this->assertNull(User::where('email', 'bob@y.test')->first());
    }
}
