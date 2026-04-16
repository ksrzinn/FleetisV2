<?php

namespace Tests\Feature\Identity;

use Tests\TestCase;

class PermissionConfigTest extends TestCase
{
    public function test_teams_feature_is_enabled_and_keyed_on_company_id(): void
    {
        $this->assertTrue(config('permission.teams'));
        $this->assertSame('company_id', config('permission.column_names.team_foreign_key'));
    }
}
