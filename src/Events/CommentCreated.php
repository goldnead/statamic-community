<?php

namespace Goldnead\Community\Events;

use Goldnead\Community\Models\Comment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired once per stored comment, after the transaction commits. */
class CommentCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}
}
