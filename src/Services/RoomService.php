<?php

namespace Goldnead\Community\Services;

use Goldnead\Community\Models\Room;
use Goldnead\Community\Models\RoomMembership;
use Goldnead\Teams\Support\Users;
use Illuminate\Support\Collection;

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
        return collect(config('community.rooms'))->map(fn (array $room) => Room::updateOrCreate(
            ['slug' => $room['slug']],
            [...$room, 'curated' => true],
        ));
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
}
