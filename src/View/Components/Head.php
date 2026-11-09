<?php

namespace Lamentor\View\Components;

use Illuminate\Contracts\View\View;

/** The <head> part of a document: stylesheet links, fonts and CSS. */
class Head extends Document
{
    public function render(): View
    {
        return view('lamentor::components.head');
    }
}
