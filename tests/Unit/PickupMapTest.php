<?php

test('pickup pages show a map when a station is chosen', function () {
    $root = dirname(__DIR__, 2);

    $map = file_get_contents($root.'/resources/js/components/maps/PickupMap.vue');
    $form = file_get_contents($root.'/resources/js/pages/admin/pickup-locations/Form.vue');
    $create = file_get_contents($root.'/resources/js/pages/user/bookings/Create.vue');
    $edit = file_get_contents($root.'/resources/js/pages/user/bookings/Edit.vue');
    $show = file_get_contents($root.'/resources/js/pages/user/packages/Show.vue');

    expect($map)->toContain('https://tile.openstreetmap.org/');

    foreach ([$form, $create, $edit, $show] as $page) {
        expect($page)
            ->toContain("import PickupMap from '@/components/maps/PickupMap.vue'")
            ->toContain('<PickupMap');
    }

    expect($form)->toContain('interactive');
    expect($create)->toContain('interactive')
        ->and($create)->not->toContain(':zoomable="false"')
        ->and($create)->toContain('Zoom in, then drag the pin');
    expect($edit)->toContain('Zoom in, then drag the pin');
    expect($map)->toContain('onPinPointerDown');
});
