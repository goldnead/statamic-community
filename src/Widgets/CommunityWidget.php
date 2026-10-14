<?php

namespace Goldnead\Community\Widgets;

use Goldnead\Community\Support\Stats;
use Illuminate\Support\Facades\Gate;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class CommunityWidget extends Widget
{
    protected static $handle = 'community';

    public static function title()
    {
        return __('Community');
    }

    public function component()
    {
        if (! Gate::allows('view community')) {
            return;
        }

        return VueComponent::render('community-widget', [
            'title' => $this->config('title', __('Community')),
            'url' => cp_route('community.posts.index'),
            'stats' => Stats::summary(),
        ]);
    }
}
