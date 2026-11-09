<?php

namespace Lamentor\Elements;

/**
 * One node of an Elementor-format document tree:
 * {id, elType, widgetType?, settings, elements, isInner}.
 */
final class Element
{
    /**
     * @param array<string,mixed> $settings
     * @param list<Element> $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $widgetType,
        public readonly array $settings,
        public readonly array $children,
        public readonly bool $isInner = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $children = array_map(
            fn (array $child) => self::fromArray($child),
            $data['elements'] ?? []
        );

        return new self(
            id: (string) ($data['id'] ?? bin2hex(random_bytes(4))),
            type: (string) ($data['elType'] ?? 'widget'),
            widgetType: $data['widgetType'] ?? null,
            // Elementor stores empty settings as [] (PHP) or {} / [] in JSON.
            settings: is_array($data['settings'] ?? null) ? $data['settings'] : [],
            children: $children,
            isInner: (bool) ($data['isInner'] ?? false),
        );
    }

    /** @return list<Element> */
    public static function documentFromJson(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return array_map(fn (array $node) => self::fromArray($node), $data);
    }
}
