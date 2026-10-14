<?php

namespace Goldnead\Community\Scopes;

class CommunityHidden extends ContentFilter
{
    protected static $handle = 'community_hidden';

    public static function title()
    {
        return __('Visibility');
    }

    public function fieldItems()
    {
        return [
            'hidden' => [
                'type' => 'select',
                'placeholder' => __('Any visibility'),
                'options' => ['no' => __('Visible'), 'yes' => __('Hidden')],
            ],
        ];
    }

    public function apply($query, $values)
    {
        $hidden = $this->value($values, 'hidden');

        in_array($hidden, ['yes', 'no'], true) && $query->where('hidden', $hidden === 'yes');
    }

    public function badge($values)
    {
        return match ($this->value($values, 'hidden')) {
            'yes' => __('Visibility').': '.__('Hidden'),
            'no' => __('Visibility').': '.__('Visible'),
            default => null,
        };
    }
}
