<?php

namespace Goldnead\Community\Services;

use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Room;
use Goldnead\Community\Models\RoomMembership;
use Goldnead\Teams\Support\Users;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoomService
{
    /**
     * Create or update the curated rooms from `community.rooms`, keyed by slug.
     * Safe to run on every deploy: a second run changes nothing.
     *
     * @return Collection<int, Room>
     */
    public function syncCurated(): Collection
    {
        return collect(config('community.rooms'))->map(function (array $room) {
            $this->assertKind((string) ($room['kind'] ?? 'topic'));

            return Room::updateOrCreate(
                ['slug' => $room['slug']],
                [...$room, 'curated' => true],
            );
        });
    }

    /**
     * Create or change a room. The one place a `kind` is accepted from outside
     * (the Control Panel), so the one place it is checked.
     *
     * @param  array{name?: string, slug?: string, kind?: string, icon?: string|null, description?: string|null}  $attributes
     */
    public function save(Room $room, array $attributes): Room
    {
        isset($attributes['kind']) && $this->assertKind((string) $attributes['kind']);

        $room->fill(array_intersect_key($attributes, array_flip(['name', 'slug', 'kind', 'icon', 'description'])));
        $room->slug = Str::slug((string) ($room->slug ?: $room->name));
        $room->curated = true;
        $room->save();

        return $room;
    }

    /** Pin one published post of the room, or clear the pin with `null`. */
    public function pin(Room $room, ?Post $post): Room
    {
        if ($post !== null && ($post->room_id !== $room->id || ! $post->isVisible())) {
            throw CommunityException::unknown('pin', (string) $post->id);
        }

        $room->update(['pinned_post_id' => $post?->id]);

        return $room;
    }

    public function join(Room $room, mixed $user, string $notify = 'digest'): RoomMembership
    {
        return RoomMembership::updateOrCreate(
            ['room_id' => $room->id, 'user_id' => Users::key($user)],
            ['notify' => $notify],
        );
    }

    public function leave(Room $room, mixed $user): void
    {
        RoomMembership::where('room_id', $room->id)->where('user_id', Users::key($user))->delete();
    }

    protected function assertKind(string $kind): void
    {
        in_array($kind, Room::KINDS, true) || throw CommunityException::unknown('room kind', $kind);
    }
}
