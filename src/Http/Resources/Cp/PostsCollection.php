<?php

namespace Goldnead\Community\Http\Resources\Cp;

use Statamic\CP\Column;
use Statamic\CP\Columns;

class PostsCollection extends ContentCollection
{
    public $collects = ListedPost::class;

    protected function defineColumns(): Columns
    {
        return new Columns([
            Column::make('title')->label(__('Post'))->sortable(true)->defaultOrder(1),
            Column::make('room')->label(__('Room'))->sortable(false)->defaultOrder(2),
            Column::make('region')->label(__('Region'))->sortable(true)->defaultOrder(3),
            Column::make('author')->label(__('Author'))->sortable(false)->defaultOrder(4),
            Column::make('status')->label(__('Status'))->sortable(true)->defaultOrder(5),
            Column::make('comments')->label(__('Comments'))->numeric(true)->sortable(false)->defaultOrder(6),
            Column::make('published_at')->label(__('Published'))->sortable(true)->defaultOrder(7),
            Column::make('created_at')->label(__('Created'))->sortable(true)->defaultOrder(8)->defaultVisibility(false)->visible(false),
        ]);
    }
}
