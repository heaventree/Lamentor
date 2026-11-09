<?php

namespace Lamentor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Lamentor\Forms\FormDefinition;
use Lamentor\Models\Document;
use Lamentor\Models\FormSubmission;
use Lamentor\Schema\SchemaRepository;

/** Public endpoint for the Form widget. The form definition always comes from the stored document. */
class FormController extends Controller
{
    public function submit(Request $request, Document $document, string $element, SchemaRepository $schema): JsonResponse|RedirectResponse
    {
        // Only published documents take submissions.
        abort_unless($document->status === 'publish', 404);

        $node = $this->find($document->data ?? [], $element);
        abort_unless($node && ($node['widgetType'] ?? null) === 'form', 404);

        $def = FormDefinition::fromSettings($node['settings'] ?? [], $schema->collectDefaults($schema->widgetControls('form')));

        // Bots fill the hidden field: pretend it worked and store nothing.
        if ($request->filled('lm_hp')) {
            return $this->done($request, $def, $element);
        }

        $validator = Validator::make($request->only('fields'), $def->rules(), [], $def->attributes());
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $key => $messages) {
                $errors[substr($key, strlen('fields.'))] = $messages[0];
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $errors], 422);
            }
            $request->session()->put("lamentor.form.{$element}.errors", $errors);
            $request->session()->put("lamentor.form.{$element}.old", (array) $request->input('fields', []));

            return redirect()->to(url()->previous() . '#' . "lm-form-{$element}");
        }

        // Keep only the declared fields.
        $data = [];
        foreach ($def->fields as $f) {
            $data[$f['id']] = ['label' => $f['label'], 'value' => $f['type'] === 'checkbox' ? ($request->boolean("fields.{$f['id']}") ? 'Yes' : 'No') : (string) $request->input("fields.{$f['id']}", '')];
        }

        FormSubmission::create([
            'document_id' => $document->id,
            'element_id' => $element,
            'form_name' => $def->name,
            'data' => $data,
            'ip' => config('lamentor.forms.store_ip', false) ? $request->ip() : null,
        ]);

        $this->notify($def, $data, $document);

        return $this->done($request, $def, $element);
    }

    private function done(Request $request, FormDefinition $def, string $element): JsonResponse|RedirectResponse
    {
        $redirect = preg_match('#^(/(?!/)|https?://)#i', $def->redirect) ? $def->redirect : null;

        if ($request->expectsJson()) {
            return response()->json(['message' => $def->successMessage, 'redirect' => $redirect]);
        }

        $request->session()->forget(["lamentor.form.{$element}.errors", "lamentor.form.{$element}.old"]);
        $request->session()->flash("lamentor.form.{$element}.sent", true);

        return redirect()->to($redirect ?? (url()->previous() . '#' . "lm-form-{$element}"));
    }

    private function notify(FormDefinition $def, array $data, Document $document): void
    {
        $to = filter_var($def->emailTo, FILTER_VALIDATE_EMAIL);
        if (! $to || ! config('lamentor.forms.mail', true)) {
            return;
        }

        // No CR/LF from stored values ever reaches a header.
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $def->subject)) ?: 'New form submission';
        $body = "{$def->name} ({$document->title})\n\n";
        foreach ($data as $field) {
            $body .= $field['label'] . ': ' . $field['value'] . "\n";
        }

        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            // The submission is saved either way; a mail outage must not lose it.
            Log::warning('Lamentor form mail failed: ' . $e->getMessage());
        }
    }

    private function find(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
            if ($hit = $this->find($node['elements'] ?? [], $id)) {
                return $hit;
            }
        }

        return null;
    }
}
