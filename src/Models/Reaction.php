<?php

namespace Goldnead\Community\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One per account and target (post or comment).
 *
 * @property int $id
 * @property string $user_id
 * @property string $target_type post|comment
 * @property int $target_id
 * @property string $kind
 */
class Reaction extends Model
{
    protected $table = 'community_reactions';

    protected $guarded = ['id'];
}
