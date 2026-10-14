<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Community\Events\PostCreated;
use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Reaction;
use Goldnead\Community\Models\Room;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Services\ReactionService;
use Goldnead\Community\Services\RoomService;
use Goldnead\Community\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/** The open points of the audit of 2026-10-14. */
class AuditPointsTest extends TestCase
{
    private function confirmed(string $email = 'singer@example.test')
    {
        $user = $this->makeUser($email);
        $user->set('email_verified_at', now()->toIso8601String())->save();

        return $user;
    }

    private function moderator()
    {
        $user = $this->confirmed('mod@example.test');
        $user->set('super', true)->save();

        return $user;
    }

    public function test_the_feed_has_a_composite_index(): void
    {
        $indexes = collect(Schema::getIndexes('community_posts'))->pluck('columns');

        $this->assertTrue(
            $indexes->contains(['status', 'hidden', 'published_at']),
            'community_posts needs an index on (status, hidden, published_at)',
        );
    }

    public function test_two_moderators_approving_the_same_post_publish_it_once(): void
    {
        config(['community.pre_moderation' => true]);
        $post = app(PostService::class)->create($this->confirmed(), '<p>Wartet</p>');
        $this->assertSame(Post::PENDING, $post->status);

        // Two moderators loaded the pending post before either of them clicked.
        $first = Post::findOrFail($post->id);
        $second = Post::findOrFail($post->id);

        Event::fake([PostCreated::class]);
        $moderator = $this->moderator();
        app(PostService::class)->approve($first, $moderator);
        app(PostService::class)->approve($second, $moderator);

        Event::assertDispatchedTimes(PostCreated::class, 1);
        $this->assertSame(Post::PUBLISHED, $post->fresh()->status);
    }

    public function test_a_double_click_on_a_reaction_returns_the_existing_one(): void
    {
        $user = $this->confirmed();
        $post = app(PostService::class)->create($user, '<p>Hallo</p>');

        // No wrapping transaction: Laravel then takes no savepoint and the
        // competing row survives the failed insert.
        DB::commit();

        // The second request wins the race between the lookup and the insert.
        $raced = false;
        Reaction::creating(function (Reaction $reaction) use (&$raced, $user) {
            if ($raced) {
                return;
            }
            $raced = true;
            DB::table('community_reactions')->insert([
                'user_id' => (string) $user->id(), 'target_type' => 'post', 'target_id' => $reaction->target_id,
                'kind' => 'like', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        // Laravel looks the row up again after a failed insert. On MySQL
        // inside a surrounding transaction that read still sees the old
        // snapshot and finds nothing; here the second lookup is blinded.
        $lookups = 0;
        Reaction::addGlobalScope('snapshot', function ($query) use (&$lookups) {
            ++$lookups === 2 && $query->whereRaw('1 = 0');
        });

        $reaction = app(ReactionService::class)->react($post, $user, 'thanks');

        $this->assertTrue($raced);
        $this->assertSame(1, Reaction::count());
        $this->assertSame('thanks', $reaction->kind);
        $this->assertSame('thanks', Reaction::first()->kind);
    }

    public function test_an_author_who_lost_confirmation_cannot_edit_old_content(): void
    {
        $user = $this->confirmed();
        $post = app(PostService::class)->create($user, '<p>Alt</p>');

        $user->remove('email_verified_at')->save();

        $this->expectException(AuthorizationException::class);
        app(PostService::class)->update($post, $user, '<p>Neu</p>');
    }

    public function test_an_unknown_room_kind_is_refused_when_syncing(): void
    {
        config(['community.rooms' => [['slug' => 'x', 'name' => 'X', 'kind' => 'galaxy']]]);

        $this->expectException(CommunityException::class);
        app(RoomService::class)->syncCurated();
    }

    public function test_an_unknown_room_kind_is_refused_when_saving(): void
    {
        $this->expectException(CommunityException::class);

        app(RoomService::class)->save(new Room, ['slug' => 'x', 'name' => 'X', 'kind' => 'galaxy']);
    }
}
