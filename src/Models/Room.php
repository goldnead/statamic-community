<?php

namespace Goldnead\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $kind region|style|topic
 * @property string|null $icon
 * @property string|null $description
 * @property bool $curated
 * @property int|null $pinned_post_id
 * @property Carbon|null $archived_at
 */
class Room extends Model
{
    public const KINDS = ['region', 'style', 'topic'];

    protected $table = 'community_rooms';

    protected $guarded = ['id'];

    protected $casts = [
        'curated' => 'boolean',
        'archived_at' => 'datetime',
    ];

    /** @return HasMany<RoomMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(RoomMembership::class);
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
