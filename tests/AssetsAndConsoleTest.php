<?php

namespace Lamentor\Tests;

use Illuminate\Filesystem\Filesystem;
use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Assets;
use Lamentor\Support\FrontendConfig;
use Orchestra\Testbench\TestCase;

class AssetsAndConsoleTest extends TestCase
{
    private string $tmp;

    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    /** The package installs into public/: point it at a throw-away folder, not the shared skeleton. */
    protected function defineEnvironment($app): void
    {
        $app->usePublicPath(sys_get_temp_dir() . '/lamentor-test-public');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/lamentor-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tmp);
        (new Filesystem())->deleteDirectory(sys_get_temp_dir() . '/lamentor-test-public');
        parent::tearDown();
    }

    private function fakeElementor(): string
    {
        $root = $this->tmp . '/elementor';
        foreach (['assets/css', 'assets/js', 'assets/lib/swiper/v8', 'assets/images', 'assets/other'] as $dir) {
            mkdir("{$root}/{$dir}", 0777, true);
        }
        file_put_contents("{$root}/elementor.php", '<?php // fake');
        file_put_contents("{$root}/assets/css/frontend.min.css", '.a{}');
        file_put_contents("{$root}/assets/js/frontend.min.js", '1');
        file_put_contents("{$root}/assets/js/frontend.min.js.map", 'map');
        file_put_contents("{$root}/assets/lib/swiper/v8/swiper.min.js", '1');
        file_put_contents("{$root}/assets/images/placeholder.png", 'png');
        file_put_contents("{$root}/assets/other/skipped.txt", 'x');

        return $root;
    }

    public function test_install_assets_copies_whitelisted_folders_only(): void
    {
        $this->artisan('lamentor:install-assets', ['source' => $this->fakeElementor()])->assertSuccessful();

        $base = public_path('vendor/lamentor/elementor/assets');
        $this->assertFileExists("{$base}/css/frontend.min.css");
        $this->assertFileExists("{$base}/lib/swiper/v8/swiper.min.js");
        $this->assertFileExists("{$base}/images/placeholder.png");
        $this->assertFileDoesNotExist("{$base}/js/frontend.min.js.map");
        $this->assertFileDoesNotExist("{$base}/other/skipped.txt");
    }

    public function test_install_assets_rejects_a_wrong_folder(): void
    {
        $this->artisan('lamentor:install-assets', ['source' => $this->tmp])->assertFailed();
    }

    public function test_render_command_outputs_a_page(): void
    {
        $file = $this->tmp . '/tpl.json';
        file_put_contents($file, json_encode(['title' => 'Cmd', 'content' => [
            ['id' => 'a', 'elType' => 'container', 'settings' => [], 'elements' => [
                ['id' => 'b', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'From CLI'], 'elements' => []],
            ]],
        ]]));
        $out = $this->tmp . '/out.html';

        $this->artisan('lamentor:render', ['file' => $file, '--out' => $out])->assertSuccessful();

        $html = file_get_contents($out);
        $this->assertStringContainsString('From CLI', $html);
        $this->assertStringContainsString('frontend.min.css', $html);
    }

    public function test_scripts_include_only_the_libraries_a_document_needs(): void
    {
        $doc = $this->app->make(Renderer::class)->document(json_encode([
            ['id' => 'a', 'elType' => 'widget', 'widgetType' => 'image-carousel', 'settings' => [], 'elements' => []],
        ]), 1);

        $scripts = Assets::scriptsFor($doc);

        $this->assertContains('lib/swiper/v8/swiper.min.js', $scripts['before']);
        $this->assertNotContains('lib/jquery-numerator/jquery-numerator.min.js', $scripts['before']);
        $this->assertSame(['js/jquery.min.js', 'js/jquery-ui.min.js'], array_slice($scripts['before'], 0, 2));
        $this->assertSame(['js/frontend.min.js'], $scripts['after']);
    }

    public function test_frontend_config_has_breakpoints_assets_url_and_is_script_safe(): void
    {
        $config = $this->app->make(FrontendConfig::class);
        $data = $config->make(['title' => '</script><b>']);

        $this->assertSame('/vendor/lamentor/elementor/assets/', $data['urls']['assets']);
        $this->assertTrue($data['responsive']['breakpoints']['tablet']['is_enabled']);
        $this->assertFalse($data['responsive']['breakpoints']['laptop']['is_enabled']);
        $this->assertSame(767, $data['responsive']['breakpoints']['mobile']['value']);
        $this->assertStringNotContainsString('</script>', $config->json(['title' => '</script><b>']));
    }


    public function test_stylesheets_follow_icons_animations_and_hover_effects(): void
    {
        $json = json_encode([
            ['id' => 'a', 'elType' => 'widget', 'widgetType' => 'icon', 'settings' => [
                'selected_icon' => ['value' => 'fab fa-github', 'library' => 'fa-brands'],
                '_animation' => 'fadeIn', 'hover_animation' => 'grow',
            ], 'elements' => []],
            ['id' => 'b', 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => ['icon_list' => [
                ['text' => 'x', '_id' => 'i', 'selected_icon' => ['value' => 'fas fa-check', 'library' => 'fa-solid']],
            ]], 'elements' => []],
        ]);

        $files = $this->app->make(Renderer::class)->stylesheetsForJson($json);

        $this->assertContains('lib/font-awesome/css/brands.min.css', $files);
        $this->assertContains('lib/font-awesome/css/solid.min.css', $files);
        $this->assertSame(1, count(array_keys($files, 'lib/font-awesome/css/fontawesome.min.css', true)));
        $this->assertContains('lib/animations/animations.min.css', $files);
        $this->assertContains('lib/animations/styles/e-animation-grow.min.css', $files);
        $this->assertContains('css/widget-icon-list.min.css', $files);
        $this->assertNotContains('lib/font-awesome/css/regular.min.css', $files);
    }

    public function test_unsafe_animation_names_never_become_paths(): void
    {
        $json = json_encode([['id' => 'a', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['hover_animation' => '../../x'], 'elements' => []]]);

        $files = $this->app->make(Renderer::class)->stylesheetsForJson($json);

        $this->assertSame([], array_filter($files, fn ($f) => str_contains($f, '..')));
    }

    public function test_bundled_jquery_is_the_official_build(): void
    {
        $this->assertSame(
            '/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=',
            base64_encode(hash_file('sha256', __DIR__ . '/../resources/js/jquery.min.js', true))
        );
    }
}
