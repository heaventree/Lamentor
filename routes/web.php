<?php

use Illuminate\Support\Facades\Route;
use Lamentor\Http\Controllers\DocumentController;
use Lamentor\Http\Controllers\EditorAssetController;
use Lamentor\Http\Controllers\LibraryController;
use Lamentor\Http\Controllers\MediaController;
use Lamentor\Http\Controllers\PageController;
use Lamentor\Http\Controllers\SchemaController;

Route::get('editor-assets/{file}', [EditorAssetController::class, 'show'])->name('lamentor.editor.asset');
Route::get('editor/{document}', [PageController::class, 'editor'])->name('lamentor.editor');
Route::get('preview/{document}', [PageController::class, 'preview'])->name('lamentor.preview');

Route::prefix('api')->group(function () {
    Route::get('schema/widgets', [SchemaController::class, 'widgets'])->name('lamentor.schema.widgets');
    Route::get('schema/controls/{type}', [SchemaController::class, 'controls'])->name('lamentor.schema.controls');
    Route::get('kit', [SchemaController::class, 'kit'])->name('lamentor.kit');
    Route::post('render', [SchemaController::class, 'render'])->name('lamentor.render');
    Route::post('media', [MediaController::class, 'store'])->name('lamentor.media.store');

    Route::get('documents', [DocumentController::class, 'index'])->name('lamentor.documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('lamentor.documents.store');
    Route::post('documents/import', [DocumentController::class, 'import'])->name('lamentor.documents.import');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('lamentor.documents.show');
    Route::put('documents/{document}', [DocumentController::class, 'update'])->name('lamentor.documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('lamentor.documents.destroy');
    Route::get('documents/{document}/export', [DocumentController::class, 'export'])->name('lamentor.documents.export');
    Route::get('documents/{document}/revisions', [DocumentController::class, 'revisions'])->name('lamentor.documents.revisions');
    Route::post('documents/{document}/revisions/{revision}/restore', [DocumentController::class, 'restore'])->name('lamentor.documents.restore');

    Route::get('library', [LibraryController::class, 'index'])->name('lamentor.library.index');
    Route::post('library/blocks', [LibraryController::class, 'store'])->name('lamentor.library.store');
    Route::get('library/{source}/{id}', [LibraryController::class, 'show'])->name('lamentor.library.show')
        ->where(['source' => '[a-z0-9][a-z0-9-]*', 'id' => '[A-Za-z0-9][A-Za-z0-9._-]*']);
    Route::get('library/{source}/{id}/thumbnail', [LibraryController::class, 'thumbnail'])->name('lamentor.library.thumbnail')
        ->where(['source' => '[a-z0-9][a-z0-9-]*', 'id' => '[A-Za-z0-9][A-Za-z0-9._-]*']);
});
