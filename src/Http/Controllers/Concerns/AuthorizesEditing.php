<?php

namespace Lamentor\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesEditing
{
    /**
     * Routes already sit behind the configured middleware (auth by default).
     * Define the `lamentor.edit` gate to restrict editing further.
     */
    protected function authorizeEditing(): void
    {
        if (Gate::has('lamentor.edit')) {
            Gate::authorize('lamentor.edit');
        }
    }
}
