<?php

namespace Goldnead\Community\Services;

use Goldnead\Community\Events\PostCreated;
use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Reaction;
use Goldnead\Community\Models\Room;
use Goldnead\Community\Policies\CommunityPolicy;
use Goldnead\Community\Support\Sanitizer;
use Goldnead\Teams\Models\Team;
use Goldnead\Teams\Support\Users;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Every write goes through here, and every write is authorised by
 * {@see CommunityPolicy}.
 */
class PostService
{
    public function __construct(protected Sanitizer $sanitizer) {}

    /**
     * Post as a person, or as `$team` when the author holds `post as team` in it.
     * With pre-moderation on, the post waits for {@see approve()} unless the
     * author is a moderator or already trusted.
     */
    public function create(mixed $user, string $body, ?Room $room = null, string $category = 'community', string $region = '', string $title = '', ?Team $team = null): Post
    {
        Gate::forUser($user)->authorize('post', [Post::class, $team]);

        if ($room?->isArchived()) {
            throw CommunityException::archivedRoom();
        }
        in_array($category, config('community.categories'), true) || throw CommunityException::unknown('category', $category);

        $key = Users::key($user);
        $published = $this->publishesDirectly($user, $key);

        $post = DB::transaction(function () use ($key, $team, $room, $category, $region, $title, $body, $published) {
            return Post::create([
                'author_user_id' => $key,
                'author_team_id' => $team?->id,
                'room_id' => $room?->id,
                'category' => $category,
                'region' => mb_substr($region, 0, 255),
                'title' => mb_substr(trim(strip_tags($title)), 0, 255),
                'body' => $this->clean($body),
                'status' => $published ? Post::PUBLISHED : Post::PENDING,
                'published_at' => $published ? now() : null,
            ]);
        });

        $published && PostCreated::dispatch($post);

        return $post;
    }

    public function update(Post $post, mixed $user, string $body, ?string $title = null): Post
    {
        Gate::forUser($user)->authorize('update', $post);

        $post->update(array_filter(['body' => $this->clean($body), 'title' => $title === null ? null : mb_substr(trim(strip_tags($title)), 0, 255)], fn ($v) => $v !== null));

        return $post;
    }

    public function delete(Post $post, mixed $user): void
    {
        Gate::forUser($user)->authorize('delete', $post);

        DB::transaction(function () use ($post) {
            $commentIds = $post->comments()->pluck('id');
            Reaction::where('target_type', Comment::TYPE)->whereIn('target_id', $commentIds)->delete();
            Reaction::where('target_type', Post::TYPE)->where('target_id', $post->id)->delete();
            Room::where('pinned_post_id', $post->id)->update(['pinned_post_id' => null]);
            $post->delete();
        });
    }

    /** A moderator releases a post that pre-moderation held back. */
    public function approve(Post $post, mixed $moderator): Post
    {
        Gate::forUser($moderator)->authorize('moderate', Post::class);

        // Conditional in the database, not on the loaded model: of two
        // moderators who approve at once only one update matches the pending
        // row, so only one of them announces the post.
        $published = now();
        $claimed = Post::where('id', $post->id)->where('status', Post::PENDING)
            ->update(['status' => Post::PUBLISHED, 'published_at' => $published, 'updated_at' => $published]);

        $post->refresh();

        $claimed === 1 && PostCreated::dispatch($post);

        return $post;
    }

    /** Moderation: hide or restore a post or comment. Nothing is deleted. */
    public function hide(Post|Comment $target, mixed $moderator, bool $hidden = true, ?string $reason = null): void
    {
        Gate::forUser($moderator)->authorize('moderate', Post::class);

        $reason = $hidden ? mb_substr(trim(strip_tags((string) $reason)), 0, 255) : '';

        $target->update(['hidden' => $hidden, 'hidden_reason' => $reason === '' ? null : $reason]);
    }

    /**
     * The public feed, newest first. Reactions never enter the order: the
     * only sort key is when the post became public. Posts that wait for
     * approval or were hidden by moderation are not in it.
     *
     * @return Builder<Post>
     */
    public function feed(?Room $room = null, ?string $region = null): Builder
    {
        return Post::visible()
            ->when($room, fn ($q) => $q->where('room_id', $room->id))
            ->when($region, fn ($q) => $q->where('region', $region))
            // The one pinned post of a room leads; everything else stays chronological.
            ->when($room?->pinned_post_id, fn ($q, $pinned) => $q->orderByRaw('case when id = ? then 0 else 1 end', [$pinned]))
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    protected function publishesDirectly(mixed $user, string $key): bool
    {
        if (! config('community.pre_moderation') || Gate::forUser($user)->allows('moderate', Post::class)) {
            return true;
        }

        return Post::where('author_user_id', $key)->where('status', Post::PUBLISHED)->count() >= config('community.trusted_after');
    }

    protected function clean(string $body): string
    {
        $body = $this->sanitizer->clean($body);

        strlen($body) > Sanitizer::MAX_BODY && throw CommunityException::tooLong();

        return trim(strip_tags($body)) === '' ? throw CommunityException::emptyBody() : $body;
    }
}
