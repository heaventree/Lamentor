@foreach ($rendered->stylesheets as $file)
<link rel="stylesheet" href="{{ $assets }}/{{ $file }}">
@endforeach
@if ($rendered->fontsUrl)
<link rel="stylesheet" href="{{ $rendered->fontsUrl }}">
@endif
{{-- "</" is escaped so no saved value can close the style element. --}}
<style>{!! str_replace('</', '<\/', $rendered->css) !!}</style>
