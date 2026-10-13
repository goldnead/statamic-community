<?php

namespace Goldnead\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $parent_id
 * @property string $author_user_id
 * @property int|null $author_team_id
 * @property string $body Sanitised HTML.
 * @property bool $hidden
 */
class Comment extends Model
{
    public const TYPE = 'comment';

    protected $table = 'community_comments';

    protected $guarded = ['id'];

    protected $casts = ['hidden' => 'boolean'];

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
