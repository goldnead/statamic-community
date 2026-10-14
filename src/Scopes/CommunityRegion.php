<?php

namespace Goldnead\Community\Scopes;

class CommunityRegion extends ContentFilter
{
    protected static $handle = 'community_region';

    public static function title()
    {
        return __('Region');
    }

    public function fieldItems()
    {
        return [
            'region' => ['type' => 'text', 'placeholder' => __('Any region')],
        ];
    }

    public function apply($query, $values)
    {
        $region = $this->value($values, 'region');

        $region === null || $this->onPost($query, fn ($posts) => $posts->where('region', $region));
    }

    public function badge($values)
    {
        $region = $this->value($values, 'region');

        return $region === null ? null : __('Region').': '.$region;
    }
}
