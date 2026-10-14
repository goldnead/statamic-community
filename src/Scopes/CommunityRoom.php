<?php

namespace Goldnead\Community\Scopes;

use Goldnead\Community\Models\Room;

class CommunityRoom extends ContentFilter
{
    protected static $handle = 'community_room';

    public static function title()
    {
        return __('Room');
    }

    public function fieldItems()
    {
        return [
            'room' => [
                'type' => 'select',
                'placeholder' => __('Any room'),
                'options' => Room::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
        ];
    }

    public function apply($query, $values)
    {
        $room = $this->value($values, 'room');

        if ($room === null || ! ctype_digit($room)) {
            return;
        }

        $this->onPost($query, fn ($posts) => $posts->where('room_id', (int) $room));
    }

    public function badge($values)
    {
        $room = $this->value($values, 'room');
        $name = $room !== null && ctype_digit($room) ? Room::query()->whereKey((int) $room)->value('name') : null;

        return $name === null ? null : __('Room').': '.$name;
    }
}
