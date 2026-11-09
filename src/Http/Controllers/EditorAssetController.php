<?php

namespace Lamentor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/** Serves the editor's own JS/CSS straight from the package (no build step, no publish needed). */
class EditorAssetController extends Controller
{
    private const FILES = [
        'editor.js' => 'application/javascript',
        'controls.js' => 'application/javascript',
        'editor.css' => 'text/css',
    ];

    public function show(Request $request, string $file): Response
    {
        abort_unless(isset(self::FILES[$file]), 404);

        $path = __DIR__ . '/../../../resources/editor/' . $file;
        abort_unless(is_file($path), 404);

        $content = (string) file_get_contents($path);

        $response = response($content, 200, [
            'Content-Type' => self::FILES[$file] . '; charset=utf-8',
            // Always revalidate: a package upgrade must never leave a stale editor in the browser.
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag(md5($content));
        $response->isNotModified($request);

        return $response;
    }
}
