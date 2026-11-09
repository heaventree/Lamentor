<?php

namespace Lamentor\Forms;

use Illuminate\Validation\Rule;

/** The fields of one stored form widget; the server only ever trusts these, never what the browser sends. */
final class FormDefinition
{
    public const TYPES = ['text', 'email', 'textarea', 'tel', 'number', 'url', 'select', 'checkbox', 'hidden'];

    /**
     * @param list<array{id:string,type:string,label:string,required:bool,options:list<string>,default:string}> $fields
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields,
        public readonly string $emailTo,
        public readonly string $subject,
        public readonly string $successMessage,
        public readonly string $redirect,
    ) {
    }

    /** @param array<string,mixed> $settings widget settings (saved values; defaults applied for missing ones) */
    public static function fromSettings(array $settings, array $defaults = []): self
    {
        $settings += $defaults;
        $fields = [];

        foreach ((array) ($settings['form_fields'] ?? []) as $raw) {
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($raw['_id'] ?? ''));
            $type = in_array($raw['field_type'] ?? 'text', self::TYPES, true) ? $raw['field_type'] : 'text';
            if ($id === '') {
                continue;
            }
            $fields[] = [
                'id' => $id,
                'type' => $type,
                'label' => (string) ($raw['field_label'] ?? $id),
                'placeholder' => (string) ($raw['placeholder'] ?? ''),
                'required' => ($raw['required'] ?? '') === 'yes',
                'options' => array_values(array_filter(array_map('trim', explode("\n", (string) ($raw['field_options'] ?? ''))), 'strlen')),
                'default' => (string) ($raw['default_value'] ?? ''),
            ];
        }

        return new self(
            (string) ($settings['form_name'] ?? 'Form'),
            $fields,
            (string) ($settings['email_to'] ?? ''),
            (string) ($settings['email_subject'] ?? ''),
            (string) ($settings['success_message'] ?? 'Thanks, your message was sent.'),
            (string) ($settings['redirect_to']['url'] ?? ''),
        );
    }

    /** Human names for messages: "The Name field ..." instead of "fields.name". @return array<string,string> */
    public function attributes(): array
    {
        $names = [];
        foreach ($this->fields as $f) {
            $names["fields.{$f['id']}"] = $f['label'] !== '' ? $f['label'] : $f['id'];
        }

        return $names;
    }

    /** Laravel validation rules built from the stored fields. @return array<string,list<string>|string> */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->fields as $f) {
            $r = [$f['required'] && $f['type'] !== 'hidden' ? 'required' : 'nullable'];
            $rule = match ($f['type']) {
                'email' => 'email:rfc|max:254',
                'url' => 'url|max:2000',
                'number' => 'numeric',
                'tel' => 'string|max:40|regex:/^[0-9+().\\-\\s]*$/',
                'select' => $f['options'] ? Rule::in($f['options']) : 'string|max:255',
                'checkbox' => 'boolean',
                'textarea' => 'string|max:5000',
                default => 'string|max:1000',
            };
            // A string like "email:rfc|max:254" is one rule inside an array, so split it.
            array_push($r, ...(is_string($rule) ? explode('|', $rule) : [$rule]));
            $rules["fields.{$f['id']}"] = $r;
        }

        return $rules;
    }
}
