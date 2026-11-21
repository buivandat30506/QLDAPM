@php
    $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'airport' => 'M3 21h18 M5 21V7l5-3v17 M10 9h7v12 M17 14h3v7 M7 8v2 M7 13v2',
        'calendar' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2 M7 14h2 M15 14h2 M7 18h2',
        'departure' => 'M2 21h20 M3 16l6 2 12-8c2-2 0-4-2-3l-6 3-6-4-2 1 4 5-4 2-2-2-2 1z',
        'plane' => 'M22 2c-1-1-3 0-4 1l-5 5-8-3-2 2 7 5-4 4-3-1-2 2 5 3 3 3 2-2-1-3 4-4 5 7 2-2-3-8 5-5c1-1 2-3 0-4z',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-4 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'truck' => 'M1 3h14v14H1z M15 8h4l4 5v4h-8 M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0 M21 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'passenger' => 'M9 4V2h6v2 M5 4h14v18H5z M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0 M8 19v-1a4 4 0 0 1 8 0v1',
        'ticket' => 'M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4z M13 5v3 M13 11v2 M13 16v3',
        'alert' => 'M10 4a2 2 0 0 1 4 0l8 15a2 2 0 0 1-2 3H4a2 2 0 0 1-2-3z M12 9v5 M12 18h.01',
        'chart' => 'M3 3v18h18 M7 16v-4 M12 16V9 M17 16V5 M5 8l5-4 4 2 6-4',
        'wallet' => 'M20 8V5H5a3 3 0 0 1 0-6 M3 5v15a1 1 0 0 0 1 1h17V8H5a3 3 0 0 1-2-3 M21 12h-5v5h5 M18 14.5h.01',
        'account' => 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0 M16 9a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M5 19a7 7 0 0 1 14 0',
    ];
@endphp
<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$icon] }}" /></svg>
