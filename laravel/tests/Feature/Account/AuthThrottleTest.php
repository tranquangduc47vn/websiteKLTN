<?php

namespace Tests\Feature\Account;

class AuthThrottleTest extends AccountTestCase
{
    public function test_forgot_password_is_limited_per_email_and_ip(): void
    {
        $email = 'throttle.' . bin2hex(random_bytes(4)) . '@example.test';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/quen-mat-khau', ['email' => $email])->assertRedirect();
        }

        $this->post('/quen-mat-khau', ['email' => $email])->assertStatus(429);
    }
}
