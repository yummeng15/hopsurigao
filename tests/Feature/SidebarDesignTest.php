<?php

test('the sidebar has the sea wave background behind the menu', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)
        ->toContain('pointer-events-none absolute inset-0')
        ->toContain('<SeaWaves')
        ->toContain('<SidebarContent class="relative z-10');
});

test('staff portals share the light cyan shell and keep logout in the header', function () {
    $frame = file_get_contents(resource_path('js/components/portal/StaffPortalFrame.vue'));
    $superadmin = file_get_contents(resource_path('js/layouts/portal/SuperAdminPortalLayout.vue'));
    $operator = file_get_contents(resource_path('js/layouts/portal/OperatorPortalLayout.vue'));

    expect($superadmin)->toContain('<StaffPortalFrame')
        ->and($operator)->toContain('<StaffPortalFrame')
        ->and($frame)->toContain('from-white via-cyan-50')
        ->and($frame)->toContain('bg-[#00FFFF] font-semibold text-cyan-950')
        ->and($frame)->toContain('data-test="staff-header-logout"')
        ->and($frame)->not->toContain('bg-slate-900');
});

test('the desktop sidebar is not used as a mobile sheet', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)->toContain('v-if="!isMobile"');
});

test('the main content area shows the sea background behind every page', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $background = file_get_contents(resource_path('js/components/SeaBackground.vue'));

    expect($layout)
        ->toContain('<SeaBackground />')
        ->toContain('relative z-10')
        ->toContain('[&>.bg-background]:bg-transparent')
        ->not->toContain('fixed inset-0');

    expect($background)
        ->toContain('pointer-events-none absolute inset-0')
        ->toContain('<SeaWaves')
        ->not->toContain('fixed inset-0');
});
