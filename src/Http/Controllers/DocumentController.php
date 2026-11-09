<?php

namespace Lamentor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lamentor\Http\Controllers\Concerns\AuthorizesEditing;
use Lamentor\Models\Document;
use Lamentor\Support\ElementTreeValidator;
use Lamentor\Templates\TemplateFormat;

/** JSON API used by the editor (and usable on its own). */
class DocumentController extends Controller
{
    use AuthorizesEditing;

    public function index(): JsonResponse
    {
        $this->authorizeEditing();

        return response()->json(Document::query()->orderByDesc('updated_at')->get(['id', 'title', 'slug', 'type', 'status', 'updated_at']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEditing();

        $fields = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:lamentor_documents,slug'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:draft,publish'],
        ]);

        $document = Document::create($fields + ['data' => [], 'settings' => [], 'author_id' => $request->user()?->getAuthIdentifier()]);

        return response()->json($document, 201);
    }

    public function show(Document $document): JsonResponse
    {
        $this->authorizeEditing();

        return response()->json($document);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $this->authorizeEditing();

        $fields = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:lamentor_documents,slug,' . $document->id],
            'status' => ['sometimes', 'string', 'in:draft,publish'],
            'data' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('data', $fields)) {
            $errors = (new ElementTreeValidator())->errors($fields['data']);
            if ($errors) {
                return response()->json(['message' => 'Invalid content.', 'errors' => $errors], 422);
            }
        }

        $document->fill(array_diff_key($fields, ['data' => 1, 'settings' => 1]));

        if (array_key_exists('data', $fields) || array_key_exists('settings', $fields)) {
            $document->saveContent($fields['data'] ?? ($document->data ?? []), $fields['settings'] ?? null, $request->user()?->getAuthIdentifier());
        } else {
            $document->save();
        }

        return response()->json($document->fresh());
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorizeEditing();
        $document->delete();

        return response()->json(null, 204);
    }

    public function revisions(Document $document): JsonResponse
    {
        $this->authorizeEditing();

        return response()->json($document->revisions()->orderByDesc('id')->get(['id', 'created_at', 'author_id']));
    }

    public function restore(Document $document, int $revision): JsonResponse
    {
        $this->authorizeEditing();

        $source = $document->revisions()->findOrFail($revision);
        $document->saveContent($source->data ?? [], $source->settings);

        return response()->json($document->fresh());
    }

    /** Elementor template export (.json). */
    public function export(Document $document): JsonResponse
    {
        $this->authorizeEditing();

        return response()->json(TemplateFormat::export($document->title, $document->type, $document->data ?? [], $document->settings ?? []));
    }

    /** Create a document from an Elementor template file. */
    public function import(Request $request): JsonResponse
    {
        $this->authorizeEditing();

        $request->validate(['template' => ['required']]);

        try {
            $template = TemplateFormat::parse($request->input('template'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $errors = (new ElementTreeValidator())->errors($template['content']);
        if ($errors) {
            return response()->json(['message' => 'Invalid content.', 'errors' => $errors], 422);
        }

        $document = Document::create([
            'title' => $request->input('title', $template['title'] ?: 'Imported template'),
            'type' => $template['type'],
            'status' => 'draft',
            'data' => $template['content'],
            'settings' => $template['page_settings'],
            'author_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return response()->json($document, 201);
    }
}
