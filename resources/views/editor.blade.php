<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title ?: 'Untitled' }} — Lamentor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ $assetsUrl }}/assets/lib/eicons/css/elementor-icons.min.css">
    <link rel="stylesheet" href="{{ route('lamentor.editor.asset', 'editor.css') }}">
</head>
<body>
<div id="lm-app" aria-live="polite">Loading editor…</div>
<script>
    window.LAMENTOR = {!! json_encode([
        'api' => $api,
        'assetsUrl' => $assetsUrl,
        'previewUrl' => $previewUrl,
        'document' => [
            'id' => $document->id,
            'title' => $document->title,
            'status' => $document->status,
            'data' => $document->data ?? [],
        ],
        'backUrl' => config('lamentor.editor_back_url'),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
</script>
<script type="module" src="{{ route('lamentor.editor.asset', 'editor.js') }}"></script>
</body>
</html>
