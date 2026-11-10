<?php

namespace Lamentor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Lamentor\Http\Controllers\Concerns\AuthorizesEditing;
use Lamentor\Library\Library;
use Lamentor\Library\LibrarySource;
use Lamentor\Library\ProvidesThumbnails;
use Lamentor\Models\Document;
use Lamentor\Support\ElementTreeValidator;
use Lamentor\Templates\TemplateFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The editor's Library panel: browse blocks from every registered source, insert one, save a selection as a block. */
class LibraryController extends Controller
{
    use AuthorizesEditing;

    private const THUMB_TYPES = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    public function __construct(private readonly Library $library)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeEditing();

        $search = (string) $request->query('q', '');
        $category = $request->query('category');
        $only = $request->query('source');
        $items = [];
        $failed = [];

        foreach ($this->library->all() as $key => $source) {
            if ($only !== null && $only !== '' && $only !== $key) {
                continue;
            }
            try {
                foreach ($source->items($search, is_string($category) ? $category : null) as $raw) {
                    $item = Library::normalizeItem($raw, $key);
                    if ($item === null) {
                        continue;
                    }
                    if ($item['thumbnail'] === null && $source instanceof ProvidesThumbnails && $source->thumbnailPath($item['id'])) {
                        $item['thumbnail'] = route('lamentor.library.thumbnail', ['source' => $key, 'id' => $item['id']]);
                    }
                    $items[] = $item;
                }
            } catch (\Throwable $e) {
                // One unreachable source (a remote reservoir that is down) must not empty the whole panel.
                report($e);
                $failed[] = $key;
            }
        }

        return response()->json([
            'sources' => array_values(array_map(fn (LibrarySource $s) => ['key' => $s->key(), 'label' => $s->label()], $this->library->all())),
            'items' => $items,
            'failed' => $failed,
        ]);
    }

    /** A block ready to insert: ids re-keyed so they never collide with the page, tree validated. */
    public function show(string $source, string $id): JsonResponse
    {
        $this->authorizeEditing();

        try {
            $block = $this->library->get($source)?->get($id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The block source is unavailable.'], 502);
        }
        if (! $block) {
            return response()->json(['message' => 'Block not found.'], 404);
        }

        try {
            $template = TemplateFormat::parse(['content' => $block['content'] ?? []]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $errors = (new ElementTreeValidator())->errors($template['content']);
        if ($errors) {
            return response()->json(['message' => 'The block is not valid.', 'errors' => $errors], 422);
        }

        return response()->json(['title' => (string) ($block['title'] ?? ''), 'type' => (string) ($block['type'] ?? 'section'), 'content' => $template['content']]);
    }

    public function thumbnail(string $source, string $id): BinaryFileResponse
    {
        $this->authorizeEditing();

        $provider = $this->library->get($source);
        $path = $provider instanceof ProvidesThumbnails ? $provider->thumbnailPath($id) : null;
        $type = $path ? (self::THUMB_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null) : null;
        abort_unless($path && $type && is_file($path), 404);

        return response()->file($path, ['Content-Type' => $type, 'Cache-Control' => 'private, max-age=3600']);
    }

    /** "Save as block": stores the given elements as a section document, which the Saved blocks source lists. */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeEditing();

        $fields = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'array', 'min:1'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $template = TemplateFormat::parse(['content' => array_values($fields['content'])]);
        $errors = (new ElementTreeValidator())->errors($template['content']);
        if ($errors) {
            return response()->json(['message' => 'Invalid content.', 'errors' => $errors], 422);
        }

        $document = Document::create([
            'title' => $fields['title'],
            'type' => 'section',
            'status' => 'draft',
            'data' => $template['content'],
            'settings' => ['lamentor_library' => array_filter([
                'category' => $fields['category'] ?? null,
                'tags' => $fields['tags'] ?? null,
            ])],
            'author_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return response()->json(Library::normalizeItem([
            'id' => (string) $document->id,
            'title' => $document->title,
            'category' => $fields['category'] ?? 'Saved',
            'tags' => $fields['tags'] ?? [],
            'type' => 'section',
        ], 'saved'), 201);
    }
}
