<?php

namespace Goldnead\Community\Models;

use Goldnead\Teams\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $author_user_id
 * @property int|null $author_team_id
 * @property int|null $room_id
 * @property string $category
 * @property string $region
 * @property string $title
 * @property string $body Sanitised HTML.
 * @property string $status published|pending
 * @property bool $hidden
 * @property Carbon|null $created_at
 * @property string|null $hidden_reason
 * @property Carbon|null $published_at
 */
class Post extends Model
{
    public const PUBLISHED = 'published';

    public const PENDING = 'pending';

    public const TYPE = 'post';

    protected $table = 'community_posts';

    protected $guarded = ['id'];

    protected $casts = [
        'hidden' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** What the public may see: published and not hidden by moderation. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', self::PUBLISHED)->where('hidden', false);
    }

    public function isVisible(): bool
    {
        return $this->status === self::PUBLISHED && ! $this->getAttribute('hidden');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'author_team_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
