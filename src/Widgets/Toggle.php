<?php

namespace Lamentor\Widgets;

/** Same markup as the accordion with "toggle" class names. */
class Toggle extends Accordion
{
    protected string $kind = 'toggle';

    public static function name(): string
    {
        return 'toggle';
    }
}
