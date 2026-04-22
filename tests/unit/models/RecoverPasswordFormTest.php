<?php

namespace tests\unit\models;

use app\models\RecoverPasswordForm;
use Codeception\Test\Unit;

/**
 * Validation rule tests for RecoverPasswordForm.
 *
 * Doesn't try to test the actual user lookup (which would require a DB
 * fixture) — focuses on the rule layer that runs before lookup.
 */
class RecoverPasswordFormTest extends Unit
{
    public function testEmailMustBeValid(): void
    {
        $f = new RecoverPasswordForm([
            'username' => 'someone',
            'email'    => 'not-an-email',
        ]);
        verify($f->validate(['email']))->false();
        verify($f->errors)->arrayHasKey('email');
    }

    public function testUsernameAndEmailAreRequired(): void
    {
        $f = new RecoverPasswordForm();
        verify($f->validate(['username', 'email']))->false();
        verify($f->errors)->arrayHasKey('username');
        verify($f->errors)->arrayHasKey('email');
    }

    public function testGetUserReturnsNullForUnknownPair(): void
    {
        $f = new RecoverPasswordForm([
            'username' => 'definitely_not_a_real_user_123',
            'email'    => 'noone@example.invalid',
        ]);
        // checkUserAndEmail validator will run when calling validate()
        // but only the rule layer surfaces the missing-user error.
        $f->validate(['username', 'email']);
        verify($f->getUser())->null();
    }
}
