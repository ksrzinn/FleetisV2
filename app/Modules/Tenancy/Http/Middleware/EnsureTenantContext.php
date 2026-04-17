<?php

namespace App\Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Missing tenant context.');

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);

        DB::beginTransaction();
        DB::statement('SET LOCAL app.current_company_id = '.(int) $user->company_id);

        try {
            $response = $next($request);
            DB::commit();

            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
