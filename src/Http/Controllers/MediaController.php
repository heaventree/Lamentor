<?php

namespace Lamentor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Lamentor\Http\Controllers\Concerns\AuthorizesEditing;

/** Stores files uploaded from the editor's media controls. SVG is not accepted (it can carry script). */
class MediaController extends Controller
{
    use AuthorizesEditing;

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEditing();

        $request->validate([
            'file' => ['required', 'file', 'max:' . (int) config('lamentor.media_max_kb', 10240), 'mimes:jpg,jpeg,png,gif,webp,avif,mp4,webm,mp3,ogg,wav'],
        ]);

        $disk = (string) config('lamentor.media_disk', 'public');
        $path = $request->file('file')->store('lamentor', $disk);

        return response()->json([
            'url' => Storage::disk($disk)->url($path),
            'id' => '',
            'alt' => pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME),
            'source' => 'library',
        ], 201);
    }
}
