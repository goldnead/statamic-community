<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Accounts\Services\PersonalDataErasure;
use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Room;
use Goldnead\Community\Models\RoomMembership;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Services\RoomService;
use Goldnead\Community\Tests\TestCase;
use Goldnead\Teams\Support\Users;
use Illuminate\Support\Facades\Gate;

class MittelFehlerTest extends TestCase
{
    public function test_joining_an_archived_room_is_refused(): void
    {
        $room = Room::create(['slug' => 'alt', 'name' => 'Alt', 'kind' => 'topic', 'archived_at' => now()]);
        $user = $this->makeUser('join-archiv@example.test');

        $this->expectException(CommunityException::class);

        app(RoomService::class)->join($room, $user);
    }

    public function test_a_member_of_an_archived_room_can_still_change_the_notification(): void
    {
        $room = Room::create(['slug' => 'alt2', 'name' => 'Alt2', 'kind' => 'topic']);
        $user = $this->makeUser('join-archiv-mitglied@example.test');
        app(RoomService::class)->join($room, $user, 'digest');
        $room->update(['archived_at' => now()]);

        $membership = app(RoomService::class)->join($room->fresh(), $user, 'off');

        $this->assertSame('off', $membership->notify);
    }

    public function test_a_join_that_loses_the_insert_race_updates_the_existing_row(): void
    {
        $room = Room::create(['slug' => 'rennen', 'name' => 'Rennen', 'kind' => 'topic']);
        $user = $this->makeUser('join-rennen@example.test');
        $key = Users::key($user);

        // Simulates a parallel join: the other request inserts the row between
        // our lookup and our insert, so our insert hits the unique index.
        $raced = false;
        RoomMembership::creating(function (RoomMembership $membership) use (&$raced, $room, $key) {
            if ($raced) {
                return;
            }
            $raced = true;
            RoomMembership::create(['room_id' => $room->id, 'user_id' => $key, 'notify' => 'off']);
        });

        try {
            $membership = app(RoomService::class)->join($room, $user, 'instant');
        } finally {
            RoomMembership::flushEventListeners();
        }

        $this->assertSame('instant', $membership->notify);
        $this->assertSame(1, RoomMembership::where('room_id', $room->id)->where('user_id', $key)->count());
    }

    public function test_erasing_an_author_clears_a_room_pin_on_their_post(): void
    {
        $author = $this->makeUser('pin-autor@example.test');
        $author->set('email_verified_at', now()->toIso8601String())->save();
        $room = Room::create(['slug' => 'pin', 'name' => 'Pin', 'kind' => 'topic']);
        $post = app(PostService::class)->create($author, '<p>Angepinnt</p>', $room);
        $room->update(['pinned_post_id' => $post->id]);

        app(PersonalDataErasure::class)->erase($author);

        $this->assertNull(Room::find($room->id)->pinned_post_id);
    }

    public function test_a_failing_pin_rolls_back_the_room_save(): void
    {
        $author = $this->makeUser('rollback-autor@example.test');
        $author->set('email_verified_at', now()->toIso8601String())->save();
        $room = Room::create(['slug' => 'rollback', 'name' => 'Alt', 'kind' => 'topic', 'curated' => true]);
        $post = app(PostService::class)->create($author, '<p>Pin</p>', $room);

        // The pin is the second write of the form. Let it fail, as a post hidden a moment earlier would.
        Room::updating(function (Room $saving) {
            $saving->isDirty('pinned_post_id') && throw CommunityException::unknown('pin', 'x');
        });
        Gate::before(fn ($user, $ability) => in_array($ability, ['access cp', 'view community', 'moderate community'], true) ? true : null);

        try {
            $this->actingAs($this->makeUser('rollback-mod@example.test', 'Moderator'))
                ->patchJson('/cp/community/rooms/'.$room->id, ['name' => 'Neu', 'kind' => 'topic', 'pinned_post_id' => $post->id])
                ->assertUnprocessable();
        } finally {
            Room::flushEventListeners();
        }

        $this->assertSame('Alt', Room::find($room->id)->name);
    }
}
