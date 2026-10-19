<?php

return [
    // Local simulation tariff. Replace with a versioned fare adapter before sandbox/live sales.
    'pricing_profile' => env('BOOKING_PRICING_PROFILE', 'local-v1'),
    'currency' => env('BOOKING_CURRENCY', 'VND'),
    'base_fare_minor' => (int) env('BOOKING_BASE_FARE_MINOR', 150000000),
    'checked_baggage_minor' => (int) env('BOOKING_CHECKED_BAGGAGE_MINOR', 0),
];
