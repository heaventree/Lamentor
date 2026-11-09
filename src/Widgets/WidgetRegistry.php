<?php

namespace Lamentor\Widgets;

use InvalidArgumentException;

final class WidgetRegistry
{
    /** @var array<string,class-string<Widget>> */
    private array $widgets = [];

    /** @var array<string,class-string<Widget>> structural elements: container, section, column */
    private array $elements = [];

    /** @param class-string<Widget> $class */
    public function register(string $class): void
    {
        $this->widgets[$class::name()] = $class;
    }

    /** @param class-string<Widget> $class */
    public function registerElement(string $class): void
    {
        $this->elements[$class::name()] = $class;
    }

    /** @param class-string<Widget> $class */
    public function registerContainer(string $class): void
    {
        $this->registerElement($class);
    }

    public function widget(string $name): Widget
    {
        $class = $this->widgets[$name]
            ?? throw new InvalidArgumentException("Unknown widget type [{$name}].");

        return new $class();
    }

    public function element(string $type): ?Widget
    {
        return isset($this->elements[$type]) ? new $this->elements[$type]() : null;
    }

    public function container(): Widget
    {
        return $this->element('container') ?? throw new InvalidArgumentException('No container registered.');
    }

    public function has(string $name): bool
    {
        return isset($this->widgets[$name]);
    }

    /** @return list<string> */
    public function widgetNames(): array
    {
        return array_keys($this->widgets);
    }
}
