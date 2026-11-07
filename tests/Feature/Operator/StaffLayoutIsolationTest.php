<?php

test('staff pages resolve to their own layouts, including legacy admin pages', function () {
    $app = file_get_contents(resource_path('js/app.ts'));
    $legacy = file_get_contents(resource_path('js/layouts/portal/LegacyStaffPortalLayout.vue'));
    $superadmin = file_get_contents(resource_path('js/layouts/portal/SuperAdminPortalLayout.vue'));
    $operator = file_get_contents(resource_path('js/layouts/portal/OperatorPortalLayout.vue'));

    expect($app)->toContain("name.startsWith('superadmin/')")
        ->toContain("name.startsWith('operator/')")
        ->toContain("name.startsWith('admin/')")
        ->toContain('LegacyStaffPortalLayout');

    expect($legacy)->toContain("roleName === 'Superadmin'")
        ->toContain("roleName === 'Admin'");

    expect($superadmin)->toContain('adminNavItems');
    expect($operator)->toContain('operatorNavItems');
});