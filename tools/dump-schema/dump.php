<?php
// Included by bootstrap.php. Writes one JSON file per widget/element.

use Elementor\Plugin;

/** @return array<string,mixed> */
function lamentor_clean($v)
{
    if (is_array($v)) {
        $o = [];
        foreach ($v as $k => $x) {
            $o[$k] = lamentor_clean($x);
        }

        return $o;
    }
    if ($v instanceof Closure || is_resource($v)) {
        return null;
    }
    if (is_object($v)) {
        return method_exists($v, 'jsonSerialize') ? lamentor_clean($v->jsonSerialize()) : (string) get_class($v);
    }

    return $v;
}

function lamentor_dump_stack($instance): array
{
    // Widgets would pull the shared "common" controls via the widgets manager; those are dumped separately.
    $stack = $instance instanceof Elementor\Widget_Base ? $instance->get_stack(false) : $instance->get_stack();
    $controls = $stack['controls'];
    $out = [];
    foreach ($controls as $name => $control) {
        // Drop editor-only noise to keep the schema small.
        unset($control['label_block'], $control['separator'], $control['description_block'], $control['editor_available']);
        $out[$name] = lamentor_clean($control);
    }

    return $out;
}

require_once $root . 'includes/base/element-base.php';
require_once $root . 'includes/base/widget-base.php';
foreach (['common-base', 'common-optimized', 'common'] as $f) {
    @include_once $root . "includes/widgets/$f.php";
}
@include_once $root . 'includes/elements/repeater.php';
foreach (['section', 'column', 'container'] as $f) {
    @include_once $root . "includes/elements/$f.php";
}
foreach (glob($root . 'includes/widgets/*.php') as $f) {
    @include_once $f;
}

// Missing WordPress functions/classes must reach run.sh so it can stub them.
function lamentor_rethrow_if_missing(Throwable $e): void
{
    if (preg_match('/^(Call to undefined function|Class ".*" not found|Undefined constant)/', $e->getMessage())) {
        throw $e;
    }
}

@mkdir($out . '/widgets', 0777, true);
@mkdir($out . '/elements', 0777, true);

$report = ['ok' => [], 'failed' => []];

function lamentor_widget_class(string $file): ?string
{
    $base = basename($file, '.php');
    $candidates = [
        'Elementor\\Widget_' . str_replace(' ', '_', ucwords(str_replace(['-', '_'], ' ', $base))),
        'Elementor\\Widget_' . str_replace('-', '_', ucfirst($base)),
    ];
    foreach ($candidates as $c) {
        if (class_exists($c)) {
            return $c;
        }
    }

    return null;
}

foreach (glob($root . 'includes/widgets/*.php') as $file) {
    $base = basename($file, '.php');
    if (in_array($base, ['common-base', 'common-optimized', 'common', 'wordpress'], true)) {
        continue;
    }
    $class = lamentor_widget_class($file);
    if (! $class) {
        $report['failed'][$base] = 'class not found';
        continue;
    }
    try {
        $ref = new ReflectionClass($class);
        if ($ref->isAbstract()) {
            continue;
        }
        $w = new $class(['id' => 'x', 'elType' => 'widget', 'widgetType' => $base, 'settings' => [], 'elements' => []], ['widget_name' => $base]);
        $name = $w->get_name();
        $schema = [
            'name' => $name,
            'class' => $class,
            'title' => $w->get_title(),
            'icon' => $w->get_icon(),
            'categories' => $w->get_categories(),
            'keywords' => $w->get_keywords(),
            'controls' => lamentor_dump_stack($w),
        ];
        file_put_contents("$out/widgets/$name.json", json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $report['ok'][] = $name . ' (' . count($schema['controls']) . ')';
    } catch (Throwable $e) {
        lamentor_rethrow_if_missing($e);
        $report['failed'][$base] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    }
}

// Data tables that live in widget code rather than in controls.
try {
    $divider = new Elementor\Widget_Divider(['id' => 'x', 'elType' => 'widget', 'widgetType' => 'divider', 'settings' => [], 'elements' => []], ['widget_name' => 'divider']);
    $m = new ReflectionMethod($divider, 'get_separator_styles');
    $m->setAccessible(true);
    file_put_contents("$out/divider-styles.json", json_encode($m->invoke($divider), JSON_UNESCAPED_SLASHES));
    $report['ok'][] = 'divider-styles';
} catch (Throwable $e) {
    lamentor_rethrow_if_missing($e);
    $report['failed']['divider-styles'] = $e->getMessage();
}

// Elements and the controls shared by all widgets ("common").
foreach (['container' => 'Elementor\\Includes\\Elements\\Container', 'section' => 'Elementor\\Element_Section', 'column' => 'Elementor\\Element_Column', 'common' => 'Elementor\\Widget_Common'] as $name => $class) {
    try {
        if (! class_exists($class)) {
            $report['failed'][$name] = "class $class not found";
            continue;
        }
        $el = new $class(['id' => 'x', 'elType' => $name, 'settings' => [], 'elements' => []], ['widget_name' => $name]);
        $schema = ['name' => $name, 'class' => $class, 'controls' => lamentor_dump_stack($el)];
        file_put_contents("$out/elements/$name.json", json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $report['ok'][] = "$name (" . count($schema['controls']) . ')';
    } catch (Throwable $e) {
        lamentor_rethrow_if_missing($e);
        $report['failed'][$name] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    }
}

echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
