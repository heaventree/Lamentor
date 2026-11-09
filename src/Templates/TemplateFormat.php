<?php

namespace Lamentor\Templates;

use InvalidArgumentException;

/**
 * Elementor's template export format:
 * {"version":"0.4","title":"..","type":"page","content":[...elements...],"page_settings":{}}
 * Content is re-keyed on import so imported ids never collide with existing ones.
 */
final class TemplateFormat
{
    /**
     * @return array{title:string,type:string,content:array,page_settings:array}
     */
    public static function parse(string|array $source): array
    {
        $data = is_string($source) ? json_decode($source, true) : $source;

        if (! is_array($data)) {
            throw new InvalidArgumentException('Template is not valid JSON.');
        }

        // Some exports are a bare element list.
        if (array_is_list($data) && (! $data || isset($data[0]['elType']))) {
            $data = ['content' => $data];
        }

        if (! isset($data['content']) || ! is_array($data['content'])) {
            throw new InvalidArgumentException('Template has no "content" element list.');
        }

        return [
            'title' => (string) ($data['title'] ?? ''),
            'type' => (string) ($data['type'] ?? 'page'),
            'content' => self::regenerateIds($data['content']),
            'page_settings' => is_array($data['page_settings'] ?? null) ? $data['page_settings'] : [],
        ];
    }

    /** @return array<string,mixed> */
    public static function export(string $title, string $type, array $content, array $pageSettings = []): array
    {
        return [
            'version' => '0.4',
            'title' => $title,
            'type' => $type,
            'content' => $content,
            'page_settings' => (object) $pageSettings,
        ];
    }

    /** New random 7-hex ids for every element (Elementor's own id shape). */
    public static function regenerateIds(array $elements): array
    {
        foreach ($elements as &$element) {
            if (! is_array($element)) {
                continue;
            }
            $element['id'] = substr(bin2hex(random_bytes(4)), 0, 7);
            if (! empty($element['elements'])) {
                $element['elements'] = self::regenerateIds($element['elements']);
            }
        }

        return $elements;
    }
}
