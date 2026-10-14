<?php

namespace Goldnead\Community\Http\Controllers\Cp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Http\Requests\FilteredRequest;

abstract class Controller extends CpController
{
    /**
     * Through the Gate, which is where Statamic resolves permissions and lets
     * super users through. The route carries `can:` as well; this is the
     * second lock, for the day a route of someone's own points at an action.
     */
    protected function authorizeOrFail(string $permission): void
    {
        abort_unless(Gate::allows($permission), 403);
    }

    protected function can(string $permission): bool
    {
        return Gate::allows($permission);
    }

    protected function searchTerm(FilteredRequest $request): ?string
    {
        $term = trim((string) $request->get('search', $request->get('q', '')));

        return $term === '' ? null : $term;
    }

    /**
     * `%` and `_` are wildcards in LIKE: a search for "50%" would otherwise
     * match everything. The ESCAPE clause is spelled out because SQLite, unlike
     * MySQL, has no default escape character; it is `!` rather than a backslash,
     * which MySQL would read as an escape inside the string literal. Column names
     * come from the callers' fixed lists, the value stays bound.
     *
     * @param  Builder<*>  $query
     * @param  list<string>  $columns
     */
    protected function applySearch(Builder $query, array $columns, string $term): void
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        $query->where(function (Builder $q) use ($columns, $escaped) {
            foreach ($columns as $column) {
                $q->orWhereRaw($column." LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            }
        });
    }

    /**
     * `sort` comes from the query string: a positive list, never a pass-through.
     *
     * @param  list<string>  $sortable
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    protected function order(FilteredRequest $request, array $sortable, string $default): array
    {
        $column = (string) $request->get('sort', $default);
        $direction = strtolower((string) $request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [in_array($column, $sortable, true) ? $column : $default, $direction];
    }
}
