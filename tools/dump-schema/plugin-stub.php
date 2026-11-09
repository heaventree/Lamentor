<?php

namespace Elementor;

/**
 * Stand-in for Elementor's Plugin singleton. The real one wires up the whole
 * WordPress-dependent plugin; the schema dumper only needs a few managers.
 */
#[\AllowDynamicProperties]
class Plugin
{
    public const ELEMENTOR_DEFAULT_POST_TYPES = ['page', 'post'];

    public static $instance;
}
