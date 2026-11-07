<?php

test('the admin sidebar links to the booking calendar', function () {
    $navigation = file_get_contents(dirname(__DIR__, 2).'/resources/js/navigation/admin.ts');

    expect($navigation)
        ->toContain("title: 'Booking Calendar'")
        ->toContain("href: '/admin/bookings/calendar'")
        ->toContain('CalendarDays');
});
