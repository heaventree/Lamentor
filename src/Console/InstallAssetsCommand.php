<?php

namespace Lamentor\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Copies Elementor's static front-end assets (CSS, JS, icon fonts, images, shapes)
 * from a local copy of the Elementor plugin into public/. They are GPLv3 files
 * that Lamentor renders against; they are not bundled in the package because of
 * their size.
 */
class InstallAssetsCommand extends Command
{
    protected $signature = 'lamentor:install-assets
        {source : Path to an unpacked Elementor plugin folder (the one containing elementor.php)}
        {--force : Overwrite files that already exist}';

    protected $description = "Copy Elementor's front-end assets into public/vendor/lamentor/elementor";

    /** Sub-folders of the plugin's assets/ directory that the front end needs. */
    private const FOLDERS = ['css', 'js', 'lib', 'images', 'shapes', 'mask-shapes', 'fonts'];

    public function handle(Filesystem $files): int
    {
        $source = rtrim(str_replace('\\', '/', (string) $this->argument('source')), '/');

        if (! $files->isFile("{$source}/elementor.php") || ! $files->isDirectory("{$source}/assets")) {
            $this->components->error("{$source} does not look like the Elementor plugin folder (no elementor.php / assets/).");

            return self::FAILURE;
        }

        $target = public_path(trim((string) config('lamentor.assets_url_path', 'vendor/lamentor/elementor'), '/') . '/assets');
        $copied = 0;

        foreach (self::FOLDERS as $folder) {
            if (! $files->isDirectory("{$source}/assets/{$folder}")) {
                continue;
            }

            foreach ($files->allFiles("{$source}/assets/{$folder}") as $file) {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen("{$source}/assets"))), '/');

                // Skip unminified duplicates, RTL variants are kept; sourcemaps are not needed.
                if (str_ends_with($relative, '.map')) {
                    continue;
                }

                $destination = "{$target}/{$relative}";
                if ($files->exists($destination) && ! $this->option('force')) {
                    continue;
                }

                $files->ensureDirectoryExists(dirname($destination));
                $files->copy($file->getPathname(), $destination);
                $copied++;
            }
        }

        // jQuery / jQuery UI Position (WordPress normally supplies them) ship with this package.
        foreach ($files->files(__DIR__ . '/../../resources/js') as $file) {
            $destination = "{$target}/js/" . $file->getFilename();
            if ($this->option('force') || ! $files->exists($destination)) {
                $files->ensureDirectoryExists(dirname($destination));
                $files->copy($file->getPathname(), $destination);
                $copied++;
            }
        }

        $files->ensureDirectoryExists("{$target}/css");
        $files->copy(__DIR__ . '/../../resources/css/lamentor.css', "{$target}/css/lamentor.css");

        $this->components->info("Copied {$copied} files to {$target}");

        return self::SUCCESS;
    }
}
