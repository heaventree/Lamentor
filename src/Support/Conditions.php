<?php

namespace Lamentor\Support;

/**
 * Ports of Elementor's control visibility rules (Controls_Stack::is_control_visible
 * and Conditions::check).
 */
final class Conditions
{
    /**
     * @param array<string,mixed> $control
     * @param array<string,mixed> $values all settings (defaults merged in)
     * @param array<string,array<string,mixed>> $controls the whole control set
     */
    public static function visible(array $control, array $values, array $controls): bool
    {
        if (! empty($control['conditions']) && ! self::check($control['conditions'], $values)) {
            return false;
        }

        if (empty($control['condition'])) {
            return true;
        }

        foreach ($control['condition'] as $key => $expected) {
            if (! preg_match('/([a-z_\-0-9]+)(?:\[([a-z_]+)])?(!?)$/i', $key, $m)) {
                continue;
            }
            [$pure, $sub, $negative] = [$m[1], $m[2] ?? '', ($m[3] ?? '') !== ''];

            if (! isset($values[$pure])) {
                return false;
            }

            $bothResponsive = isset($control['responsive']) && ! empty($controls[$pure]['responsive']);
            $nameToCheck = $pure;

            if ($bothResponsive) {
                $suffix = self::deviceSuffix($control);
                $nameToCheck = $pure . $suffix;
                $actual = $values[$pure . $suffix] ?? $values[$pure];
            } else {
                $actual = $values[$pure];
            }

            if ($sub !== '' && is_array($actual)) {
                if (! isset($actual[$sub])) {
                    return false;
                }
                $actual = $actual[$sub];
            }

            // An empty responsive value falls back to the parent (wider) device.
            if (! $actual) {
                $parent = $controls[$nameToCheck]['parent'] ?? false;
                while ($parent) {
                    $actual = $values[$parent] ?? null;
                    if ($actual) {
                        if (! is_array($actual)) {
                            break;
                        }
                        if ($sub !== '' && isset($actual[$sub])) {
                            $actual = $actual[$sub];
                            if ($actual !== '') {
                                break;
                            }
                        }
                    }
                    $parent = $controls[$parent]['parent'] ?? false;
                }
            }

            if (is_array($expected) && $expected) {
                $contains = in_array($actual, $expected, true);
            } elseif (is_array($actual) && $actual) {
                $contains = in_array($expected, $actual, true);
            } else {
                $contains = $actual === $expected;
            }

            if ($negative === $contains) {
                return false;
            }
        }

        return true;
    }

    /** `_tablet` for a tablet control, '' for desktop/non-responsive controls. */
    public static function deviceSuffix(array $control): string
    {
        $device = $control['responsive']['max'] ?? $control['responsive']['min'] ?? null;

        return ! $device || $device === 'desktop' ? '' : '_' . $device;
    }

    /** @param array{relation?:string,terms:list<array>} $conditions */
    public static function check(array $conditions, array $values): bool
    {
        $isOr = ($conditions['relation'] ?? '') === 'or';

        foreach ($conditions['terms'] as $term) {
            if (! empty($term['terms'])) {
                $result = self::check($term, $values);
            } else {
                preg_match('/(\w+)(?:\[(\w+)])?/', $term['name'], $parsed);
                $value = $values[$parsed[1]] ?? null;
                if (! empty($parsed[2])) {
                    $value = is_array($value) ? ($value[$parsed[2]] ?? null) : null;
                }
                $result = self::compare($value, $term['value'] ?? null, $term['operator'] ?? null);
            }

            if ($isOr && $result) {
                return true;
            }
            if (! $isOr && ! $result) {
                return false;
            }
        }

        return ! $isOr;
    }

    private static function compare(mixed $left, mixed $right, ?string $operator): bool
    {
        return match ($operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '!==' => $left !== $right,
            'in' => in_array($left, (array) $right, true),
            '!in' => ! in_array($left, (array) $right, true),
            'contains' => in_array($right, (array) $left, true),
            '!contains' => ! in_array($right, (array) $left, true),
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            default => $left === $right,
        };
    }
}
