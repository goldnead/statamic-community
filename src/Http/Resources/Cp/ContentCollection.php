<?php

namespace Goldnead\Community\Http\Resources\Cp;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Statamic\CP\Columns;
use Statamic\Http\Resources\CP\Concerns\HasRequestedColumns;

/**
 * The listing payload, built the way core builds its own: columns on every
 * response, the user's column preference applied (`setPreferred`) and the
 * requested columns honoured, so the column picker actually changes the table.
 */
abstract class ContentCollection extends ResourceCollection
{
    use HasRequestedColumns;

    protected Columns|\Illuminate\Support\Collection|null $columns = null;

    protected ?string $columnPreferenceKey = null;

    abstract protected function defineColumns(): Columns;

    public function columnPreferenceKey(string $key): self
    {
        $this->columnPreferenceKey = $key;

        return $this;
    }

    private function setColumns(): self
    {
        $columns = $this->defineColumns();

        if ($this->columnPreferenceKey) {
            $columns->setPreferred($this->columnPreferenceKey);
        }

        $this->columns = $columns->rejectUnlisted()->values();

        return $this;
    }

    public function toArray($request)
    {
        $this->setColumns();

        return $this->collection;
    }

    public function with($request)
    {
        return [
            // Read out of every response by the Listing: without it the
            // component reports "Something went wrong" over working rows.
            'meta' => ['columns' => $this->visibleColumns()],
        ];
    }
}
