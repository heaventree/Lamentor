<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?: config('app.name') }}</title>
    <x-lamentor::head :document="$rendered" />
</head>
<body class="elementor-default elementor-kit-{{ config('lamentor.kit_id', 1) }}">
    <x-lamentor::document :document="$rendered" :head="false" />
    @include('lamentor::partials.frontend-scripts', ['document' => $rendered])
</body>
</html>
