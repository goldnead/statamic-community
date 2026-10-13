<?php

namespace Goldnead\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $room_id
 * @property string $user_id
 * @property string $role member|moderator
 * @property string $notify off|digest|instant
 */
class RoomMembership extends Model
{
    protected $table = 'community_room_memberships';

    protected $guarded = ['id'];

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
