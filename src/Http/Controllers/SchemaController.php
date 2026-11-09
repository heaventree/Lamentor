<?php

namespace Lamentor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lamentor\Http\Controllers\Concerns\AuthorizesEditing;
use Lamentor\Kit\Kit;
use Lamentor\Rendering\Renderer;
use Lamentor\Schema\SchemaRepository;
use Lamentor\Support\ElementTreeValidator;
use Lamentor\Widgets\WidgetRegistry;

/** Feeds the editor: widget catalogue, control schemas, kit, and server-side rendering of fragments. */
class SchemaController extends Controller
{
    use AuthorizesEditing;

    public function widgets(SchemaRepository $schema, WidgetRegistry $registry): JsonResponse
    {
        $this->authorizeEditing();

        $out = [];
        foreach ($registry->widgetNames() as $name) {
            $widget = $schema->widget($name) ?? [];
            $out[] = [
                'name' => $name,
                'title' => $widget['title'] ?? $name,
                'icon' => $widget['icon'] ?? 'eicon-apps',
                'categories' => $widget['categories'] ?? ['basic'],
                'keywords' => $widget['keywords'] ?? [],
            ];
        }

        return response()->json($out);
    }

    /** Controls for one widget or element type, with the shared "common" ones appended for widgets. */
    public function controls(string $type, SchemaRepository $schema): JsonResponse
    {
        $this->authorizeEditing();

        $controls = in_array($type, ['container', 'section', 'column'], true)
            ? $schema->elementControls($type)
            : ($schema->widget($type) ? $schema->widgetControls($type) : null);

        abort_if($controls === null, 404);

        return response()->json([
            'type' => $type,
            'controls' => $controls,
            'defaults' => $schema->collectDefaults($controls),
        ]);
    }

    public function kit(Kit $kit): JsonResponse
    {
        $this->authorizeEditing();

        return response()->json($kit->settings());
    }

    /** Render an unsaved element tree (editor canvas). */
    public function render(Request $request, Renderer $renderer): JsonResponse
    {
        $this->authorizeEditing();

        $request->validate(['elements' => ['required', 'array'], 'document_id' => ['nullable']]);

        $errors = (new ElementTreeValidator())->errors($request->input('elements'));
        if ($errors) {
            return response()->json(['message' => 'Invalid content.', 'errors' => $errors], 422);
        }

        $document = $renderer->document($request->input('elements'), $request->input('document_id', 0));

        return response()->json([
            'html' => $document->html,
            'css' => $document->css,
            'stylesheets' => $document->stylesheets,
            'fontsUrl' => $document->fontsUrl,
        ]);
    }
}
