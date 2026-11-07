<?php

test('the landing page and login screen offer a light and dark mode switch', function () {
    $welcome = file_get_contents(resource_path('js/pages/Welcome.vue'));
    $login = file_get_contents(resource_path('js/layouts/auth/AuthSplitLayout.vue'));
    $switch = file_get_contents(resource_path('js/components/AppearanceTabs.vue'));

    expect($welcome)->toContain('<AppearanceTabs variant="segmented" />');
    expect($login)->toContain('<AppearanceTabs variant="segmented" />');
    expect($switch)->toContain('aria-label="Light mode"');
    expect($switch)->toContain('aria-label="Dark mode"');
    expect($switch)->toContain("updateAppearance('light')");
    expect($switch)->toContain("updateAppearance('dark')");
});

test('super admin, admin, and user headers place the mode switch beside notifications', function () {
    $staff = file_get_contents(resource_path('js/components/portal/StaffPortalFrame.vue'));
    $userHeader = file_get_contents(resource_path('js/components/AppSidebarHeader.vue'));
    $userSidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $userMobile = file_get_contents(resource_path('js/components/MobileBottomNav.vue'));

    expect(substr_count($staff, '<AppearanceTabs'))->toBe(1);
    expect(strpos($staff, '<AppearanceTabs variant="segmented" />'))
        ->toBeLessThan(strpos($staff, '<NotificationBell />'));
    expect($userHeader)->toContain('<AppearanceTabs variant="segmented" />');
    expect($userHeader)->toContain('<NotificationBell />');
    expect($userSidebar)->not->toContain('AppearanceTabs');
    expect($userMobile)->not->toContain('AppearanceTabs');
});
