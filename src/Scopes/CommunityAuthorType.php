<?php

namespace Goldnead\Community\Scopes;

class CommunityAuthorType extends ContentFilter
{
    protected static $handle = 'community_author_type';

    public static function title()
    {
        return __('Author');
    }

    public function fieldItems()
    {
        return [
            'author_type' => [
                'type' => 'select',
                'placeholder' => __('Person or team'),
                'options' => ['person' => __('Person'), 'team' => __('Team')],
            ],
        ];
    }

    public function apply($query, $values)
    {
        match ($this->value($values, 'author_type')) {
            'person' => $query->whereNull('author_team_id'),
            'team' => $query->whereNotNull('author_team_id'),
            default => null,
        };
    }

    public function badge($values)
    {
        return match ($this->value($values, 'author_type')) {
            'person' => __('Author').': '.__('Person'),
            'team' => __('Author').': '.__('Team'),
            default => null,
        };
    }
}
