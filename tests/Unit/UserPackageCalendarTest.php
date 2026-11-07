<?php

test('the tour package page renders the booking calendar', function () {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/user/packages/Show.vue');

    expect($page)
        ->toContain("import MonthCalendar, { type CalendarMarker } from '@/components/bookings/MonthCalendar.vue'")
        ->toContain('<MonthCalendar')
        ->toContain('Tour Departure Time');
});
