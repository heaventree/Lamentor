<?php

namespace Lamentor\Support;

/**
 * Structural validation of an element tree coming from the editor / an import.
 * It checks shape and size only; widget settings are rendered through allowlists
 * (see Widgets\Widget), and the raw "html" widget is trusted by design.
 */
final class ElementTreeValidator
{
    private const TYPES = ['container', 'section', 'column', 'widget'];

    public function __construct(private readonly int $maxDepth = 30, private readonly int $maxNodes = 5000)
    {
    }

    /** @return list<string> human-readable problems; empty when valid */
    public function errors(mixed $tree): array
    {
        $errors = [];
        $count = 0;

        if (! is_array($tree) || ($tree !== [] && ! array_is_list($tree))) {
            return ['The content must be a list of elements.'];
        }

        $this->walk($tree, 'content', 1, $count, $errors);

        return array_slice($errors, 0, 20);
    }

    private function walk(array $nodes, string $path, int $depth, int &$count, array &$errors): void
    {
        if ($depth > $this->maxDepth) {
            $errors[] = "{$path}: nested deeper than {$this->maxDepth} levels.";

            return;
        }

        foreach ($nodes as $i => $node) {
            $here = "{$path}[{$i}]";

            if (++$count > $this->maxNodes) {
                $errors[] = "More than {$this->maxNodes} elements.";

                return;
            }

            if (! is_array($node)) {
                $errors[] = "{$here}: not an element.";

                continue;
            }

            if (! isset($node['id']) || ! is_string($node['id']) || ! preg_match('/^[A-Za-z0-9_-]{1,32}$/', $node['id'])) {
                $errors[] = "{$here}: missing or invalid id.";
            }

            if (! in_array($node['elType'] ?? null, self::TYPES, true)) {
                $errors[] = "{$here}: unknown elType.";
            }

            if (($node['elType'] ?? null) === 'widget' && (! isset($node['widgetType']) || ! is_string($node['widgetType']) || ! preg_match('/^[A-Za-z0-9_-]+$/', $node['widgetType']))) {
                $errors[] = "{$here}: widget without a valid widgetType.";
            }

            if (isset($node['settings']) && ! is_array($node['settings'])) {
                $errors[] = "{$here}: settings must be an object.";
            }

            if (isset($node['elements'])) {
                if (! is_array($node['elements'])) {
                    $errors[] = "{$here}: elements must be a list.";
                } else {
                    $this->walk($node['elements'], "{$here}.elements", $depth + 1, $count, $errors);
                }
            }
        }
    }
}
