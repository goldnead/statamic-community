<?php

namespace Goldnead\Community\Http\Controllers\Cp;

use Goldnead\Community\Http\Resources\Cp\PostsCollection;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Policies\CommunityPolicy;
use Goldnead\Community\Support\Setup;
use Inertia\Inertia;
use Statamic\Facades\Scope;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;
use Statamic\Statamic;

/**
 * The posts listing. The Inertia response carries no rows: the Listing fetches
 * them from this same action as JSON, like core's own listings.
 */
class PostController extends Controller
{
    use QueriesFilters;

    /** The key the filters are registered and looked up under. */
    public const SCOPE = 'community-posts';

    public function index(FilteredRequest $request)
    {
        $this->authorizeOrFail('view community');

        if ($setup = Setup::guard(__('Posts'))) {
            return $setup;
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return $this->json($request);
        }

        $canModerate = $this->can(CommunityPolicy::MODERATE);

        return Inertia::render('community::Posts/Index', [
            'listingUrl' => cp_route('community.posts.index'),
            'actionUrl' => $canModerate ? cp_route('community.actions.run') : null,
            'filters' => Scope::filters(self::SCOPE),
            'sortColumn' => 'created_at',
            'sortDirection' => 'desc',
            'hasAny' => Post::query()->exists(),
            'roomsUrl' => cp_route('community.rooms.index'),
        ]);
    }

    protected function json(FilteredRequest $request)
    {
        $query = Post::query()->with(['room', 'team'])->withCount('comments');

        if ($term = $this->searchTerm($request)) {
            $this->applySearch($query, ['title', 'body', 'region'], $term);
        }

        $activeFilterBadges = $this->queryFilters($query, $request->filters, ['scope' => self::SCOPE]);

        [$column, $direction] = $this->order($request, ['title', 'region', 'status', 'published_at', 'created_at'], 'created_at');
        $query->orderBy($column, $direction)->orderByDesc('id');

        return (new PostsCollection($query->paginate(Statamic::cpPerPage($request->get('perPage')))))
            ->columnPreferenceKey('community.posts.columns')
            ->additional(['meta' => ['activeFilterBadges' => $activeFilterBadges]]);
    }
}
