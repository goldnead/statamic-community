<?php

namespace Goldnead\Community\Scopes;

use Goldnead\Community\Http\Controllers\Cp\PostController;
use Goldnead\Community\Models\Post;

/** Pending or published. Posts only: a comment has no review step. */
class CommunityStatus extends ContentFilter
{
    protected static $handle = 'community_status';

    public static function title()
    {
        return __('Status');
    }

    public function visibleTo($key)
    {
        return $key === PostController::SCOPE;
    }

    public function fieldItems()
    {
        return [
            'status' => [
                'type' => 'select',
                'placeholder' => __('Any status'),
                'options' => [Post::PENDING => __('Pending'), Post::PUBLISHED => __('Published')],
            ],
        ];
    }

    public function apply($query, $values)
    {
        $status = $this->value($values, 'status');

        in_array($status, [Post::PENDING, Post::PUBLISHED], true) && $query->where('status', $status);
    }

    public function badge($values)
    {
        return match ($this->value($values, 'status')) {
            Post::PENDING => __('Status').': '.__('Pending'),
            Post::PUBLISHED => __('Status').': '.__('Published'),
            default => null,
        };
    }
}
