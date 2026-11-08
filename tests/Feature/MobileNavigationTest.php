<?php

test('mobile navigation shows an isolated menu for each role', function () {
    $nav = file_get_contents(resource_path('js/components/MobileBottomNav.vue'));
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $mobile = file_get_contents(resource_path('js/navigation/mobile.ts'));

    expect($layout)->toContain('<MobileBottomNav />');
    expect($layout)->toContain('pb-mobile-nav');
    expect($nav)->toContain('Mobile primary navigation');
    expect($nav)->toContain('data-mobile-bottom-nav');
    expect($sidebar)->toContain('v-if="!isMobile"');
    expect($sidebar)->toContain('navigationItems');

    expect($mobile)->toContain('operatorMobileTabs');
    expect($mobile)->toContain('touristMobileTabs');
    expect($mobile)->toContain('adminMobileTabs');
    expect($mobile)->toContain("tabTitle: 'Scan'");
    expect($mobile)->toContain("tabTitle: 'Tours'");
    expect($mobile)->not->toContain('Island Hopping (tourist view)');
});