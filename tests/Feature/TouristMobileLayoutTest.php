<?php

test('shared tourist layout clears the fixed bottom navigation', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $content = file_get_contents(resource_path('js/components/AppContent.vue'));
    $nav = file_get_contents(resource_path('js/components/MobileBottomNav.vue'));
    $sticky = file_get_contents(resource_path('js/components/MobileStickyActionBar.vue'));

    expect($css)
        ->toContain('--mobile-bottom-nav-height')
        ->toContain('--mobile-sticky-action-height')
        ->toContain('--mobile-content-pad-bottom')
        ->toContain('.pb-mobile-nav')
        ->toContain('.tourist-page')
        ->toContain('.tourist-page--with-sticky')
        ->toContain('flex-shrink: 0');

    expect($layout)
        ->toContain('<MobileBottomNav />')
        ->toContain('pb-mobile-nav')
        ->toContain('[&>*:not(header)]:shrink-0')
        ->toContain('shrink-0');

    expect($content)
        ->toContain('h-dvh')
        ->toContain('overflow-x-hidden')
        ->toContain('overflow-y-auto');

    expect($nav)
        ->toContain('data-mobile-bottom-nav')
        ->toContain('env(safe-area-inset-bottom, 0px)');

    expect($sticky)
        ->toContain('<Teleport to="body">')
        ->toContain('bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px))')
        ->toContain('data-mobile-sticky-action');
});

test('tourist pages use shared mobile page spacing instead of overflow-x-clip roots', function () {
    $pages = [
        resource_path('js/pages/user/Dashboard.vue'),
        resource_path('js/pages/user/packages/Index.vue'),
        resource_path('js/pages/user/packages/Show.vue'),
        resource_path('js/pages/user/bookings/Index.vue'),
        resource_path('js/pages/user/bookings/Show.vue'),
        resource_path('js/pages/user/bookings/Create.vue'),
        resource_path('js/pages/user/bookings/Payment.vue'),
        resource_path('js/pages/user/bookings/Qr.vue'),
        resource_path('js/pages/user/bookings/Edit.vue'),
        resource_path('js/pages/user/bookings/Feedback.vue'),
        resource_path('js/pages/user/feedback/Index.vue'),
        resource_path('js/pages/user/history/Index.vue'),
        resource_path('js/pages/user/Settings.vue'),
        resource_path('js/pages/notifications/Index.vue'),
    ];

    foreach ($pages as $page) {
        $source = file_get_contents($page);

        expect($source)
            ->toContain('tourist-page')
            ->not->toContain('overflow-x-clip')
            ->not->toContain('min-h-screen overflow-x-clip');
    }
});

test('sticky booking and payment bars use the shared teleported action bar', function () {
    $show = file_get_contents(resource_path('js/pages/user/packages/Show.vue'));
    $create = file_get_contents(resource_path('js/pages/user/bookings/Create.vue'));
    $payment = file_get_contents(resource_path('js/pages/user/bookings/Payment.vue'));

    foreach ([$show, $create, $payment] as $source) {
        expect($source)
            ->toContain('MobileStickyActionBar')
            ->toContain('tourist-page--with-sticky')
            ->not->toContain('class="fixed inset-x-0 z-30 border-t border-border bg-background/95 px-4 py-3 backdrop-blur-md md:hidden');
    }
});
