<?php

namespace Goldnead\Community\Http\Controllers\Cp;

use Goldnead\Community\Http\Resources\Cp\CommentsCollection;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Policies\CommunityPolicy;
use Goldnead\Community\Support\Setup;
use Inertia\Inertia;
use Statamic\Facades\Scope;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;
use Statamic\Statamic;

class CommentController extends Controller
{
    use QueriesFilters;

    public const SCOPE = 'community-comments';

    public function index(FilteredRequest $request)
    {
        $this->authorizeOrFail('view community');

        if ($setup = Setup::guard(__('Comments'))) {
            return $setup;
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return $this->json($request);
        }

        return Inertia::render('community::Comments/Index', [
            'listingUrl' => cp_route('community.comments.index'),
            'actionUrl' => $this->can(CommunityPolicy::MODERATE) ? cp_route('community.actions.run') : null,
            'filters' => Scope::filters(self::SCOPE),
            'sortColumn' => 'created_at',
            'sortDirection' => 'desc',
            'hasAny' => Comment::query()->exists(),
        ]);
    }

    protected function json(FilteredRequest $request)
    {
        $query = Comment::query()->with(['post.room']);

        if ($term = $this->searchTerm($request)) {
            $this->applySearch($query, ['body'], $term);
        }

        $activeFilterBadges = $this->queryFilters($query, $request->filters, ['scope' => self::SCOPE]);

        [$column, $direction] = $this->order($request, ['hidden', 'created_at'], 'created_at');
        $query->orderBy($column, $direction)->orderByDesc('id');

        return (new CommentsCollection($query->paginate(Statamic::cpPerPage($request->get('perPage')))))
            ->columnPreferenceKey('community.comments.columns')
            ->additional(['meta' => ['activeFilterBadges' => $activeFilterBadges]]);
    }
}
