{{--
    Elementor's front-end behaviour (tabs, accordions, carousels, counters, lightbox ...).
    Include it once, at the end of <body>:  @include('lamentor::partials.frontend-scripts', ['document' => $rendered])
    Skipped when the page has nothing interactive and `lamentor.frontend_scripts` is false.
--}}
@php
    $assetsBase = rtrim((string) config('lamentor.assets_url', '/vendor/lamentor/elementor'), '/') . '/assets';
    $scripts = \Lamentor\Support\Assets::scriptsFor($document ?? null);
    $frontendConfig = app(\Lamentor\Support\FrontendConfig::class)->json($post ?? []);
@endphp
@if (config('lamentor.frontend_scripts', true))
@foreach ($scripts['before'] as $file)
<script src="{{ $assetsBase }}/{{ $file }}"></script>
@endforeach
<script>var elementorFrontendConfig = {!! $frontendConfig !!};</script>
@foreach ($scripts['after'] as $file)
<script src="{{ $assetsBase }}/{{ $file }}"></script>
@endforeach
@endif
