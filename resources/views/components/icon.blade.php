@props(['name' => 'gavel'])
@php
    $paths = [
        'home' => 'm3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8',
        'user' => 'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'search' => 'm21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
        'gavel' => 'm14 3 7 7-4 4-7-7 4-4M12 9l-9 9 3 3 9-9M12 21h10',
        'arrow' => 'M4 12h16m-6-6 6 6-6 6',
        'plus' => 'M12 5v14M5 12h14',
        'pin' => 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'clock' => 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0M12 6v6l4 2',
        'shield' => 'm12 2 9 4v6c0 6-9 10-9 10S3 18 3 12V6l9-4m-4 10 3 3 5-6',
        'image' => 'M3 3h18v18H3V3m0 14 6-6 4 4 3-3 5 5M8 7h.01',
        'filter' => 'M4 7h16M4 17h16M8 4v6M16 14v6',
        'check' => 'm5 12 4 4L19 6',
        'trash' => 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
        'edit' => 'm15 5 4 4M4 20l4-1L21 6l-4-4L4 15v5M13 21h8',
        'box' => 'm12 2 10 5v10l-10 5L2 17V7l10-5M2 7l10 5 10-5M12 12v10M7 4l10 5',
        'laptop' => 'M4 3h16v13H4V3M2 20h20l-2-4H4l-2 4',
        'bike' =>
            'M10 17a4 4 0 1 1-8 0 4 4 0 0 1 8 0M22 17a4 4 0 1 1-8 0 4 4 0 0 1 8 0M6 17l4-9 8 9M10 8h6M8 5h4M16 4h3v4',
        'watch' => 'M8 7V2h8v5M8 17v5h8v-5M18 12a6 6 0 1 1-12 0 6 6 0 0 1 12 0M12 8v4l2 1',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $paths[$name] ?? $paths['box'] }}" />
</svg>
