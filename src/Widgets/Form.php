<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Forms\FormDefinition;
use Lamentor\Rendering\Renderer;

/**
 * Original Lamentor widget (not an Elementor port): a contact/lead form. Submissions are validated against
 * the stored field definitions, saved, and optionally e-mailed; see Http\Controllers\FormController.
 */
class Form extends Widget
{
    public static function name(): string
    {
        return 'form';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $def = FormDefinition::fromSettings($settings, $this->defaults());
        $docId = $renderer->documentId();
        $action = $docId ? route('lamentor.forms.submit', ['document' => $docId, 'element' => $element->id]) : '#';

        $session = request()->hasSession() ? request()->session() : null;
        $errors = $session?->get("lamentor.form.{$element->id}.errors", []) ?? [];
        $old = $session?->get("lamentor.form.{$element->id}.old", []) ?? [];
        $sent = $session?->get("lamentor.form.{$element->id}.sent");

        $html = '';
        foreach ($def->fields as $f) {
            $id = "lm-{$element->id}-{$f['id']}";
            $name = "fields[{$f['id']}]";
            $value = (string) ($old[$f['id']] ?? $f['default']);
            $req = $f['required'] ? ['required' => true, 'aria-required' => 'true'] : [];

            $control = match ($f['type']) {
                'textarea' => $this->el('textarea', ['id' => $id, 'name' => $name, 'class' => ["{$p}-field", "{$p}-field-textual"], 'rows' => 5, 'placeholder' => $f['placeholder']] + $req, e($value)),
                'select' => $this->el('select', ['id' => $id, 'name' => $name, 'class' => "{$p}-field"] + $req,
                    implode('', array_map(fn ($o) => $this->el('option', ['value' => $o, 'selected' => $o === $value ? true : null], e($o)), $f['options']))),
                'checkbox' => '<input ' . $this->attrs(['type' => 'checkbox', 'id' => $id, 'name' => $name, 'value' => '1', 'checked' => $value === '1' ? true : null] + $req) . '>',
                'hidden' => '<input ' . $this->attrs(['type' => 'hidden', 'name' => $name, 'value' => $f['default']]) . '>',
                default => '<input ' . $this->attrs(['type' => $f['type'], 'id' => $id, 'name' => $name, 'class' => ["{$p}-field", "{$p}-field-textual"], 'placeholder' => $f['placeholder'], 'value' => $value] + $req) . '>',
            };

            if ($f['type'] === 'hidden') {
                $html .= $control;

                continue;
            }

            $label = $f['label'] !== '' ? $this->el('label', ['for' => $id, 'class' => "{$p}-field-label"], e($f['label'])) : '';
            $error = isset($errors[$f['id']]) ? $this->el('span', ['class' => 'lamentor-form-error', 'role' => 'alert'], e($errors[$f['id']])) : '';
            $html .= $this->el('div', ['class' => ["{$p}-field-group", "{$p}-field-type-{$f['type']}"]],
                $f['type'] === 'checkbox' ? $control . $label . $error : $label . $control . $error);
        }

        $message = $sent
            ? $this->el('div', ['class' => 'lamentor-form-message lamentor-form-success', 'role' => 'status'], e($def->successMessage))
            : ($errors ? $this->el('div', ['class' => 'lamentor-form-message', 'role' => 'alert'], 'Please check the highlighted fields.') : '');

        $honeypot = '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave empty <input type="text" name="lm_hp" tabindex="-1" autocomplete="off"></label></div>';
        $token = request()->hasSession() ? '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">' : '';
        $button = $this->el('div', ['class' => "{$p}-button-wrapper"],
            $this->el('button', ['type' => 'submit', 'class' => ["{$p}-button", "{$p}-size-sm"]], $this->el('span', ['class' => "{$p}-button-text"], e((string) ($settings['button_text'] ?? 'Send')))));

        return $this->el('form', ['id' => "lm-form-{$element->id}", 'class' => ["{$p}-form", 'lamentor-form'], 'method' => 'post', 'action' => $action, 'novalidate' => null],
            $token . $honeypot . $html . $button . $message);
    }
}
