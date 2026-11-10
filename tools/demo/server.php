<?php

/**
 * Router script for PHP's built-in server: boots Lamentor inside an Orchestra Testbench
 * Laravel app so the editor can be tried without a full Laravel project.
 *
 *   php -S 127.0.0.1:8100 -t vendor/orchestra/testbench-core/laravel/public tools/demo/server.php
 *
 * Set LAMENTOR_LIBRARY_DIR to a folder of block files to try the Library panel with them.
 *
 * Authentication is switched off here (the demo app has no users): never expose this server.
 */

use Illuminate\Http\Request;
use Orchestra\Testbench\Foundation\Application as TestbenchApplication;

$public = realpath(__DIR__ . '/../../vendor/orchestra/testbench-core/laravel/public');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Static files (installed Elementor assets, demo pages) are served directly.
if ($path !== '/' && is_file($public . $path)) {
    return false;
}

require __DIR__ . '/../../vendor/autoload.php';

$tmp = sys_get_temp_dir() . '/lamentor-demo';
@mkdir($tmp . '/sessions', 0777, true);
$database = $tmp . '/demo.sqlite';
touch($database);

// Config must exist before Lamentor registers, so a tiny provider sets it first.
$demoConfig = [
    'database.default' => 'sqlite',
    'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true],
    'session.driver' => 'file',
    'session.files' => $tmp . '/sessions',
    'app.key' => 'base64:' . base64_encode(str_repeat('d', 32)),
    'lamentor.middleware' => ['web'],
    'lamentor.assets_url' => '/vendor/lamentor/elementor',
    'filesystems.disks.public.root' => $tmp . '/uploads',
    'filesystems.disks.public.url' => '/uploads',
    // Optional folder of block files for the editor's Library panel (see src/Library/DirectorySource.php).
    'lamentor.library.directory' => getenv('LAMENTOR_LIBRARY_DIR') ?: null,
];

class DemoConfigProvider extends Illuminate\Support\ServiceProvider
{
    public static array $values = [];

    public function register(): void
    {
        config(self::$values);
    }
}
DemoConfigProvider::$values = $demoConfig;

$app = TestbenchApplication::create(
    basePath: null,
    options: ['extra' => ['providers' => [DemoConfigProvider::class, Lamentor\LamentorServiceProvider::class]]],
);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

if (! Illuminate\Support\Facades\Schema::hasTable('lamentor_documents')) {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
}

// Uploaded media is served from the temp folder.
if (str_starts_with($path, '/uploads/')) {
    $file = $tmp . '/uploads/' . substr($path, strlen('/uploads/'));
    if (is_file($file) && ! str_contains($path, '..')) {
        header('Content-Type: ' . (mime_content_type($file) ?: 'application/octet-stream'));
        readfile($file);

        return true;
    }
}

// Convenience: "/" lists documents and offers to create a demo one.
if ($path === '/') {
    $docs = Lamentor\Models\Document::query()->orderBy('id')->get();
    if (($_GET['new'] ?? '') === '1') {
        $template = Lamentor\Templates\TemplateFormat::parse(file_get_contents(__DIR__ . '/demo-template.json'));
        $doc = Lamentor\Models\Document::create(['title' => 'Demo page', 'type' => 'page', 'status' => 'draft', 'data' => $template['content'], 'settings' => []]);
        header('Location: /lamentor/editor/' . $doc->id);

        return true;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Lamentor demo</title><body style="font:15px system-ui;padding:30px"><h1>Lamentor demo</h1><ul>';
    foreach ($docs as $d) {
        echo "<li>#{$d->id} {$d->title} — <a href=\"/lamentor/editor/{$d->id}\">edit</a> · <a href=\"/lamentor/preview/{$d->id}\">preview</a></li>";
    }
    echo '</ul><p><a href="/?new=1">Create a demo page and open the editor</a></p>';

    return true;
}

$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);

return true;
