<?php

namespace Goldnead\Community\Http\Resources\Cp;

use Statamic\CP\Column;
use Statamic\CP\Columns;

class CommentsCollection extends ContentCollection
{
    public $collects = ListedComment::class;

    protected function defineColumns(): Columns
    {
        return new Columns([
            Column::make('excerpt')->label(__('Comment'))->sortable(false)->defaultOrder(1),
            Column::make('post')->label(__('Post'))->sortable(false)->defaultOrder(2),
            Column::make('room')->label(__('Room'))->sortable(false)->defaultOrder(3),
            Column::make('author')->label(__('Author'))->sortable(false)->defaultOrder(4),
            Column::make('hidden')->label(__('Visibility'))->sortable(true)->defaultOrder(5),
            Column::make('created_at')->label(__('Created'))->sortable(true)->defaultOrder(6),
            Column::make('region')->label(__('Region'))->sortable(false)->defaultOrder(7)->defaultVisibility(false)->visible(false),
        ]);
    }
}
