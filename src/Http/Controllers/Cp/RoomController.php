<?php

namespace Goldnead\Community\Http\Controllers\Cp;

use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Room;
use Goldnead\Community\Policies\CommunityPolicy;
use Goldnead\Community\Services\RoomService;
use Goldnead\Community\Support\RoomBlueprint;
use Goldnead\Community\Support\Setup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Statamic\CP\Column;
use Statamic\CP\PublishForm;

/**
 * Rooms. Few and curated, so the listing hands over all rows and lets the
 * client-side Listing search and sort them. Create and edit are core's own
 * publish form, driven by a blueprint.
 */
class RoomController extends Controller
{
    public function __construct(protected RoomService $rooms) {}

    public function index()
    {
        $this->authorizeOrFail('view community');

        if ($setup = Setup::guard(__('Rooms'))) {
            return $setup;
        }

        $rows = Room::query()
            ->withCount(['memberships', 'posts' => fn ($q) => $q->visible()])
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'icon' => $room->icon,
                'kind' => $room->kind,
                'kind_label' => RoomBlueprint::kinds()[$room->kind] ?? $room->kind,
                'members' => $room->getAttribute('memberships_count'),
                'posts' => $room->getAttribute('posts_count'),
                'pinned' => $room->pinned_post_id ? $this->postTitle(Post::query()->find($room->pinned_post_id)) : null,
                'edit_url' => cp_route('community.rooms.edit', $room->id),
            ])
            ->values()
            ->all();

        return Inertia::render('community::Rooms/Index', [
            'rooms' => $rows,
            'columns' => [
                Column::make('name')->label(__('Name'))->sortable(true),
                Column::make('kind_label')->label(__('Kind'))->sortable(true),
                Column::make('members')->label(__('Members'))->numeric(true)->sortable(true),
                Column::make('posts')->label(__('Posts'))->numeric(true)->sortable(true),
                Column::make('pinned')->label(__('Pinned post'))->sortable(false),
            ],
            'createUrl' => cp_route('community.rooms.create'),
            'canManage' => $this->can(CommunityPolicy::MODERATE),
        ]);
    }

    public function create()
    {
        $this->authorizeOrFail(CommunityPolicy::MODERATE);

        return PublishForm::make(RoomBlueprint::make(false))
            ->title(__('Create room'))
            ->icon('groups')
            ->values(['kind' => 'topic'])
            ->submittingTo(cp_route('community.rooms.store'), 'POST');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeOrFail(CommunityPolicy::MODERATE);

        $data = PublishForm::make(RoomBlueprint::make(false))->submit($request->all());

        try {
            $room = $this->rooms->save(new Room, $data);
        } catch (CommunityException $e) {
            throw ValidationException::withMessages(['kind' => __($e->getMessage())]);
        }

        return response()->json(['redirect' => cp_route('community.rooms.edit', $room->id)]);
    }

    public function edit(Room $room)
    {
        $this->authorizeOrFail(CommunityPolicy::MODERATE);

        return PublishForm::make(RoomBlueprint::make(true, $this->pinnable($room)))
            ->title($room->name)
            ->icon('groups')
            ->values([
                'name' => $room->name,
                'kind' => $room->kind,
                'icon' => $room->icon,
                'description' => $room->description,
                'pinned_post_id' => $room->pinned_post_id,
            ])
            ->submittingTo(cp_route('community.rooms.update', $room->id));
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $this->authorizeOrFail(CommunityPolicy::MODERATE);

        $data = PublishForm::make(RoomBlueprint::make(true, $this->pinnable($room)))->submit($request->all());

        $pinned = null;
        if (! empty($data['pinned_post_id'])) {
            $pinned = Post::query()->where('room_id', $room->id)->whereKey($data['pinned_post_id'])->first();

            if ($pinned === null || ! $pinned->isVisible()) {
                throw ValidationException::withMessages(['pinned_post_id' => __('Only a published post of this room can be pinned.')]);
            }
        }

        try {
            $this->rooms->save($room, $data);
            $this->rooms->pin($room, $pinned);
        } catch (CommunityException $e) {
            throw ValidationException::withMessages(['kind' => __($e->getMessage())]);
        }

        return response()->json(['saved' => true]);
    }

    /**
     * The newest published posts of the room. Not all of them: a room can hold
     * thousands, and a pin is almost always a recent post.
     *
     * @return array<int, string>
     */
    protected function pinnable(Room $room): array
    {
        $posts = Post::query()->visible()->where('room_id', $room->id)->orderByDesc('published_at')->limit(50)->get();

        // An older pin stays selectable, or saving the form would silently drop it.
        if ($room->pinned_post_id && ! $posts->contains('id', $room->pinned_post_id)) {
            $posts = $posts->merge(Post::query()->whereKey($room->pinned_post_id)->get());
        }

        return $posts->mapWithKeys(fn (Post $post) => [$post->id => (string) $this->postTitle($post)])->all();
    }

    protected function postTitle(?Post $post): ?string
    {
        if ($post === null) {
            return null;
        }

        return $post->title !== '' ? $post->title : Str::limit(trim(strip_tags($post->body)), 60);
    }
}
