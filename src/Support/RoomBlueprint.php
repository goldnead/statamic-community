<?php

namespace Goldnead\Community\Support;

use Goldnead\Community\Models\Room;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint;

/**
 * The room form as a blueprint, so the Control Panel renders it with core's
 * own publish form: the fields, the validation messages, the save button and
 * the unsaved-changes guard are core's.
 */
class RoomBlueprint
{
    /**
     * @param  array<int|string, string>  $pinnable  Post id => title of the posts that may be pinned.
     */
    public static function make(bool $exists, array $pinnable = []): Blueprint
    {
        $fields = [
            'name' => [
                'type' => 'text',
                'display' => __('Name'),
                'validate' => ['required', 'string', 'max:191'],
            ],
        ];

        // The slug names the room in URLs and in `community.rooms`: it is chosen once.
        if (! $exists) {
            $fields['slug'] = [
                'type' => 'text',
                'display' => __('Slug'),
                'instructions' => __('Used in URLs. Leave empty to derive it from the name. It cannot be changed later.'),
                'validate' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9][a-z0-9-]*$/', 'unique:community_rooms,slug'],
            ];
        }

        $fields['kind'] = [
            'type' => 'select',
            'display' => __('Kind'),
            'options' => self::kinds(),
            'default' => 'topic',
            'validate' => ['required', 'in:'.implode(',', Room::KINDS)],
        ];
        $fields['icon'] = [
            'type' => 'text',
            'display' => __('Icon'),
            'instructions' => __('An emoji or a short symbol shown next to the name.'),
            'validate' => ['nullable', 'string', 'max:16'],
        ];
        $fields['description'] = [
            'type' => 'textarea',
            'display' => __('Description'),
            'validate' => ['nullable', 'string', 'max:2000'],
        ];

        if ($exists) {
            $fields['pinned_post_id'] = [
                'type' => 'select',
                'display' => __('Pinned post'),
                'instructions' => __('Leads the feed of this room. Only published posts of this room can be pinned.'),
                'options' => $pinnable,
                'clearable' => true,
                'placeholder' => __('No pinned post'),
                'validate' => ['nullable', 'integer'],
            ];
        }

        return BlueprintFacade::makeFromFields($fields);
    }

    /** @return array<string, string> */
    public static function kinds(): array
    {
        return ['region' => __('Region'), 'style' => __('Style'), 'topic' => __('Topic')];
    }
}
