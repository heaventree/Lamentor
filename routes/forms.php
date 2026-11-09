<?php

use Illuminate\Support\Facades\Route;
use Lamentor\Http\Controllers\FormController;

Route::post('forms/{document}/{element}', [FormController::class, 'submit'])
    ->where('element', '[A-Za-z0-9_-]+')
    ->name('lamentor.forms.submit');
