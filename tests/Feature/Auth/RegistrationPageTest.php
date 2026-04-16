<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationPageTest extends TestCase
{
    public function test_registration_screen_renders(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }
}
