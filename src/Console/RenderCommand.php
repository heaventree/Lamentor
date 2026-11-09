<?php

namespace Lamentor\Console;

use Illuminate\Console\Command;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Assets;
use Lamentor\Support\FrontendConfig;
use Lamentor\Templates\TemplateFormat;

/** Renders an Elementor template/page JSON file to a standalone HTML page. Handy for checking an export. */
class RenderCommand extends Command
{
    protected $signature = 'lamentor:render
        {file : Elementor template or _elementor_data JSON}
        {--out= : Write to this file instead of stdout}
        {--assets-url= : Base URL of the installed Elementor assets (default: config lamentor.assets_url)}';

    protected $description = 'Render an Elementor-format JSON file to HTML';

    public function handle(Renderer $renderer): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $template = TemplateFormat::parse((string) file_get_contents($path));
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($url = $this->option('assets-url')) {
            config(['lamentor.assets_url' => rtrim((string) $url, '/')]);
        }

        $rendered = $renderer->document($template['content'], 0);
        $assets = rtrim((string) config('lamentor.assets_url'), '/') . '/assets';

        $links = '';
        foreach ($rendered->stylesheets as $file) {
            $links .= "<link rel=\"stylesheet\" href=\"{$assets}/{$file}\">\n";
        }
        if ($rendered->fontsUrl) {
            $links .= '<link rel="stylesheet" href="' . e($rendered->fontsUrl) . "\">\n";
        }

        $scripts = Assets::scriptsFor($rendered);
        $tags = static fn (array $list): string => implode("\n", array_map(fn ($f) => "<script src=\"{$assets}/{$f}\"></script>", $list));
        $config = app(FrontendConfig::class)->json();

        $page = "<!doctype html>\n<html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . '<title>' . e($template['title'] ?: basename($path)) . "</title>\n{$links}<style>" . str_replace('</', '<\/', $rendered->css) . "</style></head>\n"
            . '<body class="elementor-default elementor-kit-' . config('lamentor.kit_id', 1) . "\">\n{$rendered->html}\n"
            . $tags($scripts['before']) . "\n<script>var elementorFrontendConfig = {$config};</script>\n" . $tags($scripts['after']) . "\n</body></html>\n";

        if ($out = $this->option('out')) {
            file_put_contents($out, $page);
            $this->components->info("Wrote {$out}");
        } else {
            $this->output->write($page);
        }

        return self::SUCCESS;
    }
}
