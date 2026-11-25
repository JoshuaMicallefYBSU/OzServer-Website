{{-- The tab and home-screen icons. Linked by path because the site may be served from above
     public/ (see App\Support\PublicUrl), where a browser's own guess at /favicon.ico finds nothing. --}}
@php
    $icons = [
        'favicon.svg' => 'rel="icon" type="image/svg+xml"',
        'favicon-32.png' => 'rel="icon" type="image/png" sizes="32x32"',
        'favicon.ico' => 'rel="shortcut icon"',
        'apple-touch-icon.png' => 'rel="apple-touch-icon"',
    ];
@endphp
@foreach ($icons as $file => $attributes)
@if ($href = \App\Support\PublicUrl::file($file))
        <link {!! $attributes !!} href="{{ $href }}">
@endif
@endforeach
