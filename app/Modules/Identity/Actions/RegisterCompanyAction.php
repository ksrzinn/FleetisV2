<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class RegisterCompanyAction
{
    public function __construct(private SeedCompanyRolesAction $seedRoles) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): User
    {
        return DB::transaction(function () use ($input) {
            $company = Company::create([
                'name' => $input['company_name'],
                'cnpj' => $input['cnpj'],
                'timezone' => 'America/Sao_Paulo',
                'status' => 'active',
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $this->seedRoles->handle($company);

            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
            $user->assignRole('Admin');

            return $user;
        });
    }
}
