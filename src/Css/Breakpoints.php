<?php

namespace Lamentor\Css;

/**
 * Elementor's breakpoint model. Desktop is the base; every other device has a
 * pixel value and a direction (max-width, or min-width for widescreen).
 */
final class Breakpoints
{
    /** Canonical order, narrowest first, as Elementor registers them. */
    private const ORDER = ['mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'widescreen'];

    private const DEFAULTS = ['mobile' => 767, 'mobile_extra' => 880, 'tablet' => 1024, 'tablet_extra' => 1200, 'laptop' => 1366, 'widescreen' => 2400];

    /** @var array<string,int> active device => px, in canonical order */
    private array $active = [];

    /**
     * @param array<string,int|null> $active device => px (null uses Elementor's default value)
     */
    public function __construct(array $active = ['mobile' => 767, 'tablet' => 1024])
    {
        foreach (self::ORDER as $device) {
            if (array_key_exists($device, $active)) {
                $this->active[$device] = (int) ($active[$device] ?? self::DEFAULTS[$device]);
            }
        }
    }

    /** Active devices, widest first (the order Elementor emits per-device controls in, minus desktop). @return list<string> */
    public function devices(): array
    {
        return array_reverse(array_keys($this->active));
    }

    public function direction(string $device): string
    {
        return $device === 'widescreen' ? 'min' : 'max';
    }

    public function value(string $device): ?int
    {
        return $this->active[$device] ?? null;
    }

    /** Pixel value for a `max-width` query on this device. */
    public function max(string $device): ?int
    {
        return $this->value($device);
    }

    /** Pixel value for a `min-width` query starting at this device. */
    public function min(string $device): ?int
    {
        if ($device === 'desktop') {
            return $this->desktopMin();
        }

        $keys = array_keys($this->active);
        if (! isset($this->active[$device])) {
            return null;
        }
        if ($keys[0] === $device) {
            return 320;
        }
        if ($this->direction($device) === 'min') {
            return $this->active[$device];
        }

        $previous = $keys[array_search($device, $keys, true) - 1];

        return $this->active[$previous] + 1;
    }

    private function desktopMin(): int
    {
        $maxes = array_filter($this->active, fn (int $v, string $d) => $this->direction($d) === 'max', ARRAY_FILTER_USE_BOTH);

        return $maxes ? max($maxes) + 1 : 1;
    }
}
