<?php

namespace Lamentor\Css;

use Lamentor\Kit\Kit;
use Lamentor\Support\Conditions;

/**
 * Port of Elementor's CSS generation (Core\Files\CSS\Base::add_control_rules and
 * add_controls_stack_style_rules) over the JSON control schemas.
 *
 * A control contributes rules when it is visible (its `condition`s hold), has a
 * non-empty value and defines `selectors`. Placeholders: {{VALUE}}, {{SIZE}},
 * {{UNIT}}, {{TOP}} ... (any key of an array value, upper-cased),
 * {{other_control.KEY}} to read another control and `{{A || B}}` fallbacks. A
 * placeholder that resolves to nothing aborts that control, as in Elementor.
 */
final class StyleGenerator
{
    private const PLACEHOLDER = '/{{(?:([^.}]+)\.)?([^}| ]*)(?: *\|\| *(?:([^.}]+)\.)?([^}| ]*) *)*}}/';

    private ?Stylesheet $sheet = null;

    public function __construct(private readonly Breakpoints $breakpoints, private readonly ?Kit $kit = null)
    {
    }

    /**
     * @param array<string,array<string,mixed>> $controls schema controls
     * @param array<string,mixed> $values settings with defaults merged in
     * @param array<string,string> $replacements e.g. ['{{WRAPPER}}' => '.elementor-1 .elementor-element-abc']
     */
    public function generate(Stylesheet $sheet, array $controls, array $values, array $replacements): void
    {
        $this->sheet = $sheet;

        // Controls driven by a global behave as "set" for visibility purposes.
        $visibility = $this->withGlobals($controls, $values);

        foreach ($controls as $name => $control) {
            if (! Conditions::visible($control, $visibility, $controls)) {
                continue;
            }

            if (($control['type'] ?? '') === 'repeater') {
                $this->repeater($sheet, $control, $values[$name] ?? [], $replacements);

                continue;
            }

            if (! empty($control['selectors'])) {
                $this->controlRules($sheet, $control, $controls, $values, $replacements);
            }
        }
    }

    private function repeater(Stylesheet $sheet, array $control, mixed $items, array $replacements): void
    {
        if (! is_array($items) || empty($control['fields'])) {
            return;
        }

        $defaults = [];
        foreach ($control['fields'] as $fieldName => $field) {
            if (array_key_exists('default', $field)) {
                $defaults[$fieldName] = $field['default'];
            }
        }

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['_id'])) {
                continue;
            }

            $this->generate(
                $sheet,
                $control['fields'],
                array_replace($defaults, $item),
                $replacements + ['{{CURRENT_ITEM}}' => '.elementor-repeater-item-' . $item['_id']],
            );
        }
    }

    private function controlRules(Stylesheet $sheet, array $control, array $controls, array $values, array $replacements): void
    {
        $globalName = ! empty($control['groupType']) ? ($control['groupPrefix'] ?? '') . $control['groupType'] : $control['name'];
        $globalKey = (string) ($values['__globals__'][$globalName] ?? '');

        $value = null;
        if ($globalKey === '') {
            $value = $this->controlValue($control, $values);
            if ($value === null) {
                return;
            }
        }

        foreach ($control['selectors'] as $selector => $cssProperty) {
            if ($globalKey !== '') {
                $global = $this->selectorGlobalValue($control, $globalKey);
                // Replace the value in "property: value;" with the variable reference.
                $output = $global ? preg_replace('/(:)[^;]+(;?)/', '$1' . addcslashes($global, '\\$') . '$2', $cssProperty) : '';
            } else {
                if (is_array($value) && isset($control['unit_selectors_dictionary'][$value['unit'] ?? ''])) {
                    $cssProperty = $control['unit_selectors_dictionary'][$value['unit']];
                }

                try {
                    $output = preg_replace_callback(
                        self::PLACEHOLDER,
                        fn (array $m) => $this->placeholder($m, $control, $controls, $values, $value),
                        $cssProperty
                    );
                } catch (\UnexpectedValueException) {
                    return; // a placeholder had no value: skip the whole control
                }
            }

            if ($output === null || $output === '' || $output === '0') {
                continue;
            }

            $query = [];
            if (preg_match('/^(?:\([^)]+\)){1,2}/', $selector, $rules)) {
                $selector = substr($selector, strlen($rules[0]));
                preg_match_all('/\(([^)]+)\)/', $rules[0], $parts);
                foreach ($parts[1] as $rule) {
                    if ($rule === 'desktop') {
                        continue;
                    }
                    $device = preg_replace('/\+$/', '', $rule);
                    $query[$device === $rule ? 'max' : 'min'] = $device;
                }
            }

            if (! $query && ! empty($control['responsive'])) {
                $query = array_intersect_key($control['responsive'], ['min' => 1, 'max' => 1]);
                if (($query['max'] ?? null) === 'desktop') {
                    unset($query['max']);
                }
            }

            $pixels = $this->toPixels($query);
            if ($pixels === null) {
                continue; // refers to a breakpoint that is not active
            }

            $sheet->add(strtr($selector, $replacements), $output, $pixels);
        }
    }

    /** @return array{min?:int,max?:int}|null */
    private function toPixels(array $query): ?array
    {
        $out = [];
        foreach ($query as $endpoint => $device) {
            $px = $endpoint === 'max' ? $this->breakpoints->max($device) : $this->breakpoints->min($device);
            if ($px === null) {
                return null;
            }
            $out[$endpoint] = $px;
        }

        return $out;
    }

    private function placeholder(array $m, array $control, array $controls, array $values, mixed $value): string
    {
        $externalMissing = ($m[1] ?? '') !== '' && ! isset($controls[$m[1]]);
        $parsed = '';

        if (! $externalMissing) {
            $parsed = $this->parse($control, $value, $controls, $values, $m[2], $m[1] ?? '');
        }

        if ($parsed === '') {
            if (isset($m[4])) {
                $parsed = $m[4];

                if (preg_match('/^([\'"])(.*)\1$/', $parsed, $string)) {
                    $parsed = $string[2];
                } elseif (! is_numeric($parsed)) {
                    if (($m[3] ?? '') !== '' && ! isset($controls[$m[3]])) {
                        return '';
                    }
                    $parsed = $this->parse($control, $value, $controls, $values, $m[4], $m[3] ?? '');
                }
            }

            if ($parsed === '') {
                if ($externalMissing) {
                    return '';
                }

                throw new \UnexpectedValueException('empty placeholder');
            }
        }

        return $parsed === '__EMPTY__' ? '' : $parsed;
    }

    private function parse(array $control, mixed $value, array $controls, array $values, string $placeholder, string $otherControl): string
    {
        if ($otherControl !== '') {
            if (! empty($control['responsive']) && ! empty($controls[$otherControl]['responsive'])) {
                $suffix = Conditions::deviceSuffix($control);
                $control = $controls[$otherControl . $suffix] ?? $controls[$otherControl];
            } else {
                $control = $controls[$otherControl];
            }
            $value = $this->controlValue($control, $values);
        }

        if (empty($value) && $value !== '0' && $value !== 0) {
            $value = $this->globalDefault($control);
        }

        if (($control['type'] ?? '') === 'font' && is_string($value) && $value !== '') {
            $this->sheet?->addFont($value);
        }

        if ($placeholder === 'DEFAULT') {
            return (string) (is_scalar($control['default'] ?? null) ? $control['default'] : '');
        }

        if (is_array($value)) {
            if (($control['type'] ?? '') !== 'media' || $placeholder !== 'URL') {
                $resolved = $value[strtolower($placeholder)] ?? '';
            } else {
                $resolved = $value['url'] ?? '';
            }
            if ($placeholder === 'UNIT' && $resolved === 'custom') {
                return '__EMPTY__';
            }

            return is_scalar($resolved) ? (string) $resolved : '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function controlValue(array $control, array $values): mixed
    {
        $value = $values[$control['name'] ?? ''] ?? null;

        if (is_scalar($value) && isset($control['selectors_dictionary'][(string) $value])) {
            $value = $control['selectors_dictionary'][(string) $value]; // schema-defined, trusted
        } else {
            $value = $this->clean($value);
        }

        if (! is_numeric($value) && empty($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Saved values end up inside a stylesheet, so nothing that could close a
     * rule or the <style> element, or run script, is let through.
     */
    private function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->clean($v), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = str_replace(['{', '}', '<', '>'], '', preg_replace('#/\*.*?\*/#s', '', $value));

        if (preg_match('/@import|expression\s*\(|javascript:|vbscript:|behavior\s*:|-moz-binding/i', $value)) {
            return '';
        }

        // A semicolon would start a new declaration; only url(...) values may contain one.
        if (str_contains($value, ';') && ! preg_match('/url\s*\(/i', $value)) {
            $value = str_replace(';', '', $value);
        }

        return $value;
    }

    /** @param array<string,array<string,mixed>> $controls */
    private function withGlobals(array $controls, array $values): array
    {
        if (empty($values['__globals__'])) {
            return $values;
        }

        foreach ($controls as $name => $control) {
            if (! empty($control['global']['active']) && ! empty($values['__globals__'][$name])) {
                $values[$name] = 'global';
            }
        }

        return $values;
    }

    /** CSS value for a control whose own value is empty but declares a global default (e.g. a brand colour). */
    private function globalDefault(array $control): ?string
    {
        if (empty($control['global']['default']) || ! $this->kit) {
            return null;
        }

        $enabled = ($control['type'] ?? '') === 'color'
            ? $this->kit->customColorsEnabled()
            : (($control['groupType'] ?? '') === 'typography' && $this->kit->customTypographyEnabled());

        return $enabled ? $this->selectorGlobalValue($control, $control['global']['default']) : null;
    }

    private function selectorGlobalValue(array $control, string $globalKey): ?string
    {
        $data = $this->kit?->resolve($globalKey);
        if (empty($data['value'])) {
            return null;
        }

        $id = explode('?id=', $globalKey)[1];

        if (empty($control['groupType'])) {
            return "var( --e-global-{$control['type']}-{$id} )";
        }

        $strip = [$control['groupPrefix']];
        foreach ($this->breakpoints->devices() as $device) {
            $strip[] = '_' . $device;
        }
        $property = str_replace($strip, '', $control['name']);

        // Same check as Elementor: whether the global defines this property at all.
        if (empty($data['value']['typography_' . $property])) {
            return null;
        }

        $value = "var( --e-global-{$control['groupType']}-{$id}-" . str_replace('_', '-', $property) . ' )';

        if ($control['groupPrefix'] . 'font_family' === $control['name'] && $this->kit->defaultGenericFonts() !== '') {
            $value .= ', ' . $this->kit->defaultGenericFonts();
        }

        return $value;
    }
}
