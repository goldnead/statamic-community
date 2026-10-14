<?php

use Goldnead\Community\Http\Controllers\Cp\CommentController;
use Goldnead\Community\Http\Controllers\Cp\ModerationActionController;
use Goldnead\Community\Http\Controllers\Cp\PostController;
use Goldnead\Community\Http\Controllers\Cp\RoomController;
use Illuminate\Support\Facades\Route;

/*
 * Every route carries `can:` middleware AND the controller checks again.
 * Viewing needs `view community`; everything that changes something needs
 * `moderate community`.
 */
Route::prefix('community')->name('community.')->group(function () {
    Route::get('posts', [PostController::class, 'index'])->name('posts.index')->middleware('can:view community');
    Route::get('comments', [CommentController::class, 'index'])->name('comments.index')->middleware('can:view community');

    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index')->middleware('can:view community');
    Route::get('rooms/create', [RoomController::class, 'create'])->name('rooms.create')->middleware('can:moderate community');
    Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store')->middleware('can:moderate community');
    Route::get('rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit')->whereNumber('room')->middleware('can:moderate community');
    Route::patch('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update')->whereNumber('room')->middleware('can:moderate community');

    // The two endpoints core's <Listing> uses for row and bulk actions.
    Route::middleware('can:moderate community')->group(function () {
        Route::post('actions', [ModerationActionController::class, 'run'])->name('actions.run');
        Route::post('actions/list', [ModerationActionController::class, 'bulkActions'])->name('actions.bulk');
    });
});
