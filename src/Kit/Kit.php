<?php

namespace Lamentor\Kit;

use Lamentor\Css\Breakpoints;
use Lamentor\Css\Stylesheet;

/**
 * Site-wide design settings (Elementor's "Kit"): global colours and typography.
 * Documents reference them through `__globals__`, e.g.
 * {"title_color": "globals/colors?id=primary"}, and the kit stylesheet
 * publishes them as CSS custom properties (--e-global-color-primary ...).
 */
final class Kit
{
    private const TYPOGRAPHY_PREFIX = 'typography_';

    /** Typography properties published as variables: setting suffix => css property. */
    private const TYPOGRAPHY_PROPERTIES = [
        'font_family' => 'font-family',
        'font_size' => 'font-size',
        'font_weight' => 'font-weight',
        'text_transform' => 'text-transform',
        'font_style' => 'font-style',
        'text_decoration' => 'text-decoration',
        'line_height' => 'line-height',
        'letter_spacing' => 'letter-spacing',
        'word_spacing' => 'word-spacing',
    ];

    /** @var array<string,mixed> */
    private array $settings;

    /** @param array<string,mixed> $settings overrides merged over Elementor's defaults */
    public function __construct(array $settings = [], private readonly int|string $id = 1)
    {
        $this->settings = array_replace(self::defaults(), $settings);
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        $type = fn (string $id, string $title, string $family, string $weight) => [
            '_id' => $id, 'title' => $title,
            'typography_typography' => 'custom', 'typography_font_family' => $family, 'typography_font_weight' => $weight,
        ];

        return [
            'system_colors' => [
                ['_id' => 'primary', 'title' => 'Primary', 'color' => '#6EC1E4'],
                ['_id' => 'secondary', 'title' => 'Secondary', 'color' => '#54595F'],
                ['_id' => 'text', 'title' => 'Text', 'color' => '#7A7A7A'],
                ['_id' => 'accent', 'title' => 'Accent', 'color' => '#61CE70'],
            ],
            'custom_colors' => [],
            'system_typography' => [
                $type('primary', 'Primary', 'Roboto', '600'),
                $type('secondary', 'Secondary', 'Roboto Slab', '400'),
                $type('text', 'Text', 'Roboto', '400'),
                $type('accent', 'Accent', 'Roboto', '500'),
            ],
            'custom_typography' => [],
            'default_generic_fonts' => 'Sans-serif',
            // Mirrors Elementor's "disable default colors/fonts" toggles.
            'custom_colors_enabled' => true,
            'custom_typography_enabled' => true,
        ];
    }

    public function id(): int|string
    {
        return $this->id;
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        return $this->settings;
    }

    public function customColorsEnabled(): bool
    {
        return (bool) $this->settings['custom_colors_enabled'];
    }

    public function customTypographyEnabled(): bool
    {
        return (bool) $this->settings['custom_typography_enabled'];
    }

    public function defaultGenericFonts(): string
    {
        return (string) $this->settings['default_generic_fonts'];
    }

    /**
     * Resolve "globals/colors?id=primary" or "globals/typography?id=primary".
     * Colours return {id, title, value: '#hex'}; typography {id, title, value: {typography_*}}.
     *
     * @return array{id:string,title:string,value:mixed}|null
     */
    public function resolve(string $key): ?array
    {
        if (! preg_match('#^globals/(colors|typography)\?id=(.+)$#', $key, $m)) {
            return null;
        }

        [$kind, $id] = [$m[1], $m[2]];
        $lists = $kind === 'colors'
            ? [$this->settings['system_colors'], $this->settings['custom_colors']]
            : [$this->settings['system_typography'], $this->settings['custom_typography']];

        foreach ($lists as $items) {
            foreach ((array) $items as $item) {
                if (($item['_id'] ?? null) !== $id) {
                    continue;
                }

                if ($kind === 'colors') {
                    return ['id' => $id, 'title' => (string) ($item['title'] ?? ''), 'value' => $item['color'] ?? ''];
                }

                $value = $item;
                unset($value['_id'], $value['title']);

                return ['id' => $id, 'title' => (string) ($item['title'] ?? ''), 'value' => $value];
            }
        }

        return null;
    }

    /** `.elementor-kit-{id}` rule that defines every global as a custom property. */
    public function css(Breakpoints $breakpoints, string $prefix = 'elementor'): string
    {
        $sheet = new Stylesheet();
        $selector = ".{$prefix}-kit-{$this->id}";

        foreach (array_merge((array) $this->settings['system_colors'], (array) $this->settings['custom_colors']) as $color) {
            if (! empty($color['_id']) && ($color['color'] ?? '') !== '') {
                $sheet->add($selector, "--e-global-color-{$color['_id']}: {$color['color']}");
            }
        }

        foreach (array_merge((array) $this->settings['system_typography'], (array) $this->settings['custom_typography']) as $type) {
            if (empty($type['_id'])) {
                continue;
            }
            $this->typographyRules($sheet, $selector, $type, $breakpoints);
        }

        return (string) $sheet;
    }

    private function typographyRules(Stylesheet $sheet, string $selector, array $type, Breakpoints $breakpoints): void
    {
        $id = $type['_id'];

        foreach (self::TYPOGRAPHY_PROPERTIES as $property => $css) {
            $key = self::TYPOGRAPHY_PREFIX . $property;
            $name = str_replace('_', '-', $property);

            foreach (['desktop' => ''] + array_combine($breakpoints->devices(), array_map(fn ($d) => "_{$d}", $breakpoints->devices())) as $device => $suffix) {
                $value = $type[$key . $suffix] ?? null;
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                if (is_array($value)) {
                    if (($value['size'] ?? '') === '') {
                        continue;
                    }
                    $value = $value['size'] . ($value['unit'] === 'custom' ? '' : ($value['unit'] ?? ''));
                } elseif ($property === 'font_family') {
                    $value = '"' . $value . '"';
                }

                $query = $device === 'desktop' ? [] : ($breakpoints->direction($device) === 'min' ? ['min' => $breakpoints->min($device)] : ['max' => $breakpoints->max($device)]);
                $sheet->add($selector, "--e-global-typography-{$id}-{$name}: {$value}", $query);
            }
        }
    }
}
