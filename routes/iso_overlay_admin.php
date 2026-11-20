<?php

use App\Http\Controllers\Admin\IsoOverlayController;
use Illuminate\Support\Facades\Route;

/*
| M8 ISO Overlay Template Engine, admin screens.
| Loaded from routes/custom_admin.php, so it inherits the admin panel prefix and
| middleware. Every action also checks its own admin_iso_overlays_* permission.
*/

Route::group(['prefix' => 'iso-overlays'], function () {
    Route::get('/', [IsoOverlayController::class, 'index'])->name('admin.iso_overlays.index');
    Route::get('/create', [IsoOverlayController::class, 'create'])->name('admin.iso_overlays.create');
    Route::post('/store', [IsoOverlayController::class, 'store'])->name('admin.iso_overlays.store');

    Route::get('/versions/{version}', [IsoOverlayController::class, 'version'])->whereNumber('version')->name('admin.iso_overlays.version');
    Route::post('/versions/{version}/update', [IsoOverlayController::class, 'updateVersion'])->whereNumber('version')->name('admin.iso_overlays.version.update');
    Route::post('/versions/{version}/publish', [IsoOverlayController::class, 'publish'])->whereNumber('version')->name('admin.iso_overlays.version.publish');
    Route::post('/versions/{version}/archive', [IsoOverlayController::class, 'archive'])->whereNumber('version')->name('admin.iso_overlays.version.archive');
    Route::post('/versions/{version}/delete', [IsoOverlayController::class, 'deleteVersion'])->whereNumber('version')->name('admin.iso_overlays.version.delete');
    Route::get('/versions/{version}/items/create', [IsoOverlayController::class, 'createItem'])->whereNumber('version')->name('admin.iso_overlays.items.create');
    Route::post('/versions/{version}/items', [IsoOverlayController::class, 'storeItem'])->whereNumber('version')->name('admin.iso_overlays.items.store');

    Route::get('/items/{item}/edit', [IsoOverlayController::class, 'editItem'])->whereNumber('item')->name('admin.iso_overlays.items.edit');
    Route::post('/items/{item}/update', [IsoOverlayController::class, 'updateItem'])->whereNumber('item')->name('admin.iso_overlays.items.update');
    Route::post('/items/{item}/delete', [IsoOverlayController::class, 'deleteItem'])->whereNumber('item')->name('admin.iso_overlays.items.delete');

    Route::post('/assignments/{assignment}/toggle', [IsoOverlayController::class, 'toggleAssignment'])->whereNumber('assignment')->name('admin.iso_overlays.assignments.toggle');

    Route::get('/{overlay}', [IsoOverlayController::class, 'show'])->whereNumber('overlay')->name('admin.iso_overlays.show');
    Route::post('/{overlay}/update', [IsoOverlayController::class, 'update'])->whereNumber('overlay')->name('admin.iso_overlays.update');
    Route::post('/{overlay}/versions/new', [IsoOverlayController::class, 'newVersion'])->whereNumber('overlay')->name('admin.iso_overlays.versions.new');
    Route::post('/{overlay}/clone', [IsoOverlayController::class, 'cloneOverlay'])->whereNumber('overlay')->name('admin.iso_overlays.clone');
    Route::post('/{overlay}/activate', [IsoOverlayController::class, 'activate'])->whereNumber('overlay')->name('admin.iso_overlays.activate');
    Route::post('/{overlay}/deactivate', [IsoOverlayController::class, 'deactivate'])->whereNumber('overlay')->name('admin.iso_overlays.deactivate');
    Route::post('/{overlay}/rollback', [IsoOverlayController::class, 'rollback'])->whereNumber('overlay')->name('admin.iso_overlays.rollback');
    Route::post('/{overlay}/assignments', [IsoOverlayController::class, 'assign'])->whereNumber('overlay')->name('admin.iso_overlays.assign');
});
