<?php

namespace Goldnead\Community\Events;

use Goldnead\Community\Models\Post;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a post becomes public, after the transaction commits: on
 * creation, or on approval when pre-moderation held it back. A post waiting
 * for approval fires nothing, so no listener can announce what nobody may see.
 */
class PostCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Post $post) {}
}
