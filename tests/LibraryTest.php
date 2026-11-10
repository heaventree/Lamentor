<?php

namespace Lamentor\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Lamentor\LamentorServiceProvider;
use Lamentor\Library\Library;
use Lamentor\Library\LibrarySource;
use Lamentor\Models\Document;
use Orchestra\Testbench\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->dir = sys_get_temp_dir() . '/lamentor-library-' . bin2hex(random_bytes(4));
        $this->writeBlock('hero', 'split-image', ['title' => 'Split hero', 'tags' => ['image', 'cta']], true);
        $this->writeBlock('pricing', 'three-tiers', null, false);
        // A folder name that is not a safe slug is ignored.
        $this->writeBlock('pricing', 'Bad Name', null, false);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('lamentor.middleware', ['web']);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $app['config']->set('lamentor.library.directory', $this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function writeBlock(string $category, string $slug, ?array $meta, bool $thumb): void
    {
        $dir = "{$this->dir}/{$category}/{$slug}";
        mkdir($dir, 0777, true);
        file_put_contents("$dir/block.json", json_encode(['version' => '0.4', 'title' => $slug, 'type' => 'section', 'content' => [$this->container('Hello ' . $slug)]]));
        if ($meta) {
            file_put_contents("$dir/meta.json", json_encode($meta));
        }
        if ($thumb) {
            file_put_contents("$dir/thumb.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        }
    }

    private function container(string $text, string $id = 'c1'): array
    {
        return ['id' => $id, 'elType' => 'container', 'isInner' => false, 'settings' => [], 'elements' => [
            ['id' => $id . 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => $text], 'elements' => []],
        ]];
    }

    public function test_index_lists_saved_and_directory_blocks(): void
    {
        Document::create(['title' => 'My CTA', 'type' => 'section', 'data' => [$this->container('CTA')]]);
        Document::create(['title' => 'A page', 'type' => 'page', 'data' => []]);

        $res = $this->getJson('/lamentor/api/library')->assertOk()->json();

        $this->assertSame(['saved', 'blocks'], array_column($res['sources'], 'key'));
        $titles = array_column($res['items'], 'title');
        $this->assertContains('My CTA', $titles);
        $this->assertNotContains('A page', $titles, 'pages are not blocks');
        $this->assertContains('Split hero', $titles);
        $this->assertContains('Three Tiers', $titles, 'title falls back to the folder name');
        $this->assertCount(3, $res['items'], 'the unsafe folder name is skipped');

        $hero = collect($res['items'])->firstWhere('id', 'hero.split-image');
        $this->assertSame('Hero', $hero['category']);
        $this->assertSame(['image', 'cta'], $hero['tags']);
        $this->assertStringEndsWith('/lamentor/api/library/blocks/hero.split-image/thumbnail', $hero['thumbnail']);
        $this->assertNull(collect($res['items'])->firstWhere('id', 'pricing.three-tiers')['thumbnail']);
    }

    public function test_index_filters_by_search_category_and_source(): void
    {
        $this->assertSame(['Split hero'], array_column($this->getJson('/lamentor/api/library?q=cta')->json('items'), 'title'));
        $this->assertSame(['Three Tiers'], array_column($this->getJson('/lamentor/api/library?category=Pricing')->json('items'), 'title'));
        $this->assertSame([], $this->getJson('/lamentor/api/library?source=saved')->json('items'));
    }

    public function test_show_returns_rekeyed_content(): void
    {
        $block = $this->getJson('/lamentor/api/library/blocks/hero.split-image')->assertOk()->json();

        $this->assertSame('Split hero', $block['title']);
        $this->assertSame('Hello split-image', $block['content'][0]['elements'][0]['settings']['title']);
        $this->assertNotSame('c1', $block['content'][0]['id'], 'ids are re-keyed so inserting twice never collides');

        $this->getJson('/lamentor/api/library/blocks/hero.missing')->assertNotFound();
        $this->getJson('/lamentor/api/library/nope/hero.split-image')->assertNotFound();
        $this->get('/lamentor/api/library/blocks/..%2F..%2Fetc')->assertNotFound();
    }

    public function test_thumbnail_is_served_only_for_known_blocks(): void
    {
        $this->get('/lamentor/api/library/blocks/hero.split-image/thumbnail')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/lamentor/api/library/blocks/pricing.three-tiers/thumbnail')->assertNotFound();
        $this->get('/lamentor/api/library/saved/1/thumbnail')->assertNotFound();
    }

    public function test_save_as_block_stores_a_section_listed_under_saved(): void
    {
        $item = $this->postJson('/lamentor/api/library/blocks', [
            'title' => 'Footer CTA', 'category' => 'CTA', 'tags' => ['footer'], 'content' => [$this->container('Talk to us')],
        ])->assertCreated()->json();

        $this->assertSame('saved', $item['source']);
        $doc = Document::findOrFail((int) $item['id']);
        $this->assertSame('section', $doc->type);
        $this->assertSame('Talk to us', $doc->data[0]['elements'][0]['settings']['title']);

        $listed = collect($this->getJson('/lamentor/api/library?source=saved')->json('items'))->firstWhere('id', $item['id']);
        $this->assertSame('CTA', $listed['category']);
        $this->assertSame(['footer'], $listed['tags']);

        $back = $this->getJson("/lamentor/api/library/saved/{$item['id']}")->assertOk()->json();
        $this->assertSame('Talk to us', $back['content'][0]['elements'][0]['settings']['title']);
    }

    public function test_save_as_block_rejects_invalid_trees(): void
    {
        $this->postJson('/lamentor/api/library/blocks', ['title' => 'Bad', 'content' => [['id' => 'x1', 'elType' => 'bogus', 'settings' => [], 'elements' => []]]])
            ->assertStatus(422);
        $this->postJson('/lamentor/api/library/blocks', ['title' => 'Empty', 'content' => []])->assertStatus(422);
        $this->assertSame(0, Document::count());
    }

    public function test_saved_source_does_not_expose_pages(): void
    {
        $page = Document::create(['title' => 'Private page', 'type' => 'page', 'data' => [$this->container('secret')]]);

        $this->getJson("/lamentor/api/library/saved/{$page->id}")->assertNotFound();
    }

    public function test_a_failing_source_is_reported_without_emptying_the_panel(): void
    {
        app(Library::class)->register(new class implements LibrarySource {
            public function key(): string { return 'remote'; }
            public function label(): string { return 'Remote'; }
            public function items(string $search = '', ?string $category = null): array { throw new \RuntimeException('down'); }
            public function get(string $id): ?array { return null; }
        });

        $res = $this->getJson('/lamentor/api/library')->assertOk()->json();

        $this->assertSame(['remote'], $res['failed']);
        $this->assertCount(2, $res['items']);
    }

    public function test_show_reports_broken_blocks_and_failing_sources_cleanly(): void
    {
        file_put_contents("{$this->dir}/pricing/three-tiers/block.json", '{not json');
        $this->getJson('/lamentor/api/library/blocks/pricing.three-tiers')->assertStatus(422);

        app(Library::class)->register(new class implements LibrarySource {
            public function key(): string { return 'remote'; }
            public function label(): string { return 'Remote'; }
            public function items(string $search = '', ?string $category = null): array { return []; }
            public function get(string $id): ?array { throw new \RuntimeException('down'); }
        });
        $this->getJson('/lamentor/api/library/remote/anything')->assertStatus(502);
    }

    public function test_library_respects_the_edit_gate(): void
    {
        Gate::define('lamentor.edit', fn ($user = null) => false);

        $this->getJson('/lamentor/api/library')->assertForbidden();
        $this->postJson('/lamentor/api/library/blocks', ['title' => 'x', 'content' => [$this->container('x')]])->assertForbidden();
    }

    public function test_source_keys_must_be_url_safe(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(Library::class)->register(new class implements LibrarySource {
            public function key(): string { return 'Bad Key'; }
            public function label(): string { return 'x'; }
            public function items(string $search = '', ?string $category = null): array { return []; }
            public function get(string $id): ?array { return null; }
        });
    }
}
