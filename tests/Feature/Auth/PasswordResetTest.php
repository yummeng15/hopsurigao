<?php

/**
 * Fortify link-based password reset was replaced by the
 * 6-digit email code flow in PasswordResetCodeTest.
 */
test('password reset uses the 6-digit code flow', function () {
    expect(route('password.request'))->toEndWith('/forgot-password');
    expect(route('password.verify'))->toEndWith('/forgot-password/verify');
    expect(route('password.reset'))->toEndWith('/reset-password');
    expect(file_exists(resource_path('js/pages/auth/VerifyPasswordResetCode.vue')))->toBeTrue();
});
