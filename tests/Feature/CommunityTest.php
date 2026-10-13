<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Accounts\PersonalData\ErasureRegistry;
use Goldnead\Accounts\Services\PersonalDataErasure;
use Goldnead\Accounts\Services\PersonalDataExport;
use Goldnead\Community\Events\CommentCreated;
use Goldnead\Community\Events\PostCreated;
use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Integrations\Accounts\CommunityContributor;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Reaction;
use Goldnead\Community\Models\Room;
use Goldnead\Community\Seeders\RoomSeeder;
use Goldnead\Community\Services\CommentService;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Services\ReactionService;
use Goldnead\Community\Support\Sanitizer;
use Goldnead\Community\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

class CommunityTest extends TestCase
{
    private $singer;

    private $newcomer;

    private $moderator;

    private $unconfirmed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->singer = $this->makeConfirmed('singer@example.test');
        $this->newcomer = $this->makeConfirmed('new@example.test');
        $this->moderator = $this->makeConfirmed('mod@example.test');
        $this->moderator->set('super', true)->save();
        $this->unconfirmed = $this->makeUser('open@example.test');
    }

    private function makeConfirmed(string $email)
    {
        $user = $this->makeUser($email);
        $user->set('email_verified_at', now()->toIso8601String())->save();

        return $user;
    }

    private function posts(): PostService
    {
        return app(PostService::class);
    }

    private function publish($user = null, string $body = '<p>Hallo Chor</p>', ?Room $room = null, string $region = ''): Post
    {
        return $this->posts()->create($user ?? $this->singer, $body, $room, region: $region);
    }

    public function test_curated_rooms_are_reproducible(): void
    {
        $this->seed(RoomSeeder::class);
        $first = Room::orderBy('id')->pluck('slug', 'id')->all();
        $this->seed(RoomSeeder::class);

        $this->assertCount(count(config('community.rooms')), $first);
        $this->assertSame($first, Room::orderBy('id')->pluck('slug', 'id')->all());
        $this->assertTrue(Room::where('slug', 'community')->first()->curated);
    }

    public function test_new_user_posts_on_fresh_install_without_pre_moderation(): void
    {
        $this->seed(RoomSeeder::class);
        Event::fake([PostCreated::class]);

        $post = $this->publish($this->newcomer, room: Room::where('slug', 'community')->first());

        $this->assertSame(Post::PUBLISHED, $post->status);
        $this->assertSame([$post->id], $this->posts()->feed()->pluck('id')->all());
        Event::assertDispatched(PostCreated::class);
    }

    public function test_unconfirmed_account_cannot_post_comment_or_react(): void
    {
        $post = $this->publish();

        foreach ([
            fn () => $this->publish($this->unconfirmed),
            fn () => app(CommentService::class)->create($post, $this->unconfirmed, 'Hi'),
            fn () => app(ReactionService::class)->react($post, $this->unconfirmed),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the write to be refused.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_pre_moderation_hides_post_until_approval(): void
    {
        config(['community.pre_moderation' => true]);
        Event::fake([PostCreated::class]);

        $post = $this->publish($this->newcomer);

        $this->assertSame(Post::PENDING, $post->status);
        $this->assertCount(0, $this->posts()->feed()->get());
        Event::assertNotDispatched(PostCreated::class);

        try {
            $this->posts()->approve($post, $this->newcomer);
            $this->fail('Only moderators approve.');
        } catch (AuthorizationException) {
        }

        $this->posts()->approve($post, $this->moderator);
        $this->assertCount(1, $this->posts()->feed()->get());
        Event::assertDispatchedTimes(PostCreated::class, 1);

        // Trusted after one published post: the second goes out directly. A moderator never waits.
        $this->assertSame(Post::PUBLISHED, $this->publish($this->newcomer)->status);
        $this->assertSame(Post::PUBLISHED, $this->publish($this->moderator)->status);
    }

    public function test_post_as_team_only_with_team_permission(): void
    {
        $admin = $this->makeConfirmed('admin@example.test');
        $team = $this->makeTeam($admin, $this->singer); // singer is a plain member

        $post = $this->posts()->create($admin, '<p>Wir suchen Tenöre</p>', team: $team);
        $this->assertSame($team->id, $post->author_team_id);
        $this->assertSame($admin->id(), $post->author_user_id);

        $this->expectException(AuthorizationException::class);
        $this->posts()->create($this->singer, '<p>Im Namen des Chors</p>', team: $team);
    }

    public function test_feed_order_ignores_reactions_and_filters_by_room_and_region(): void
    {
        $this->seed(RoomSeeder::class);
        $room = Room::where('slug', 'aushilfen')->first();
        $old = $this->publish(body: '<p>alt</p>', room: $room, region: 'BW');
        $new = $this->publish(body: '<p>neu</p>', region: 'NRW');
        $old->update(['published_at' => now()->subDay()]);

        foreach ([$this->newcomer, $this->moderator, $this->singer] as $fan) {
            app(ReactionService::class)->react($old, $fan, 'helpful');
        }

        $this->assertSame([$new->id, $old->id], $this->posts()->feed()->pluck('id')->all());
        $this->assertSame([$old->id], $this->posts()->feed($room)->pluck('id')->all());
        $this->assertSame([$new->id], $this->posts()->feed(region: 'NRW')->pluck('id')->all());
    }

    public function test_hidden_post_and_comment_are_not_public(): void
    {
        $post = $this->publish();
        $comment = app(CommentService::class)->create($post, $this->newcomer, 'Schön');

        $this->posts()->hide($comment, $this->moderator);
        $this->assertTrue($comment->fresh()->hidden);
        $this->assertCount(0, $post->comments()->where('hidden', false)->get());
        try {
            app(ReactionService::class)->react($comment, $this->singer);
            $this->fail('No reaction to hidden comments.');
        } catch (AuthorizationException) {
        }

        $this->posts()->hide($post, $this->moderator);
        $this->assertCount(0, $this->posts()->feed()->get());

        $this->posts()->hide($post, $this->moderator, false);
        $this->assertCount(1, $this->posts()->feed()->get());

        $this->expectException(AuthorizationException::class);
        $this->posts()->hide($post, $this->newcomer);
    }

    public function test_sanitiser_keeps_utf8(): void
    {
        $body = '<p>Grüße äöüß €</p>';

        $this->assertSame($body, app(Sanitizer::class)->clean($body));
        $this->assertSame($body, $this->publish(body: $body)->body);
    }

    public function test_hidden_post_is_not_visible_nor_commentable_or_reactable(): void
    {
        $post = $this->publish();
        $this->posts()->hide($post, $this->moderator);
        $post = $post->fresh();

        $this->assertFalse($post->isVisible());

        try {
            app(CommentService::class)->create($post, $this->newcomer, 'Hi');
            $this->fail('No comment on hidden post.');
        } catch (AuthorizationException) {
        }
        try {
            app(ReactionService::class)->react($post, $this->newcomer);
            $this->fail('No reaction to hidden post.');
        } catch (AuthorizationException) {
        }
    }

    public function test_html_is_sanitised(): void
    {
        $post = $this->publish(body: '<p onclick="steal()">Hi <strong>du</strong></p><script>alert(1)</script><a href="javascript:alert(1)">x</a><a href="https://example.test/x" onmouseover="y()">ok</a>');

        $this->assertStringNotContainsString('script', $post->body);
        $this->assertStringNotContainsString('onclick', $post->body);
        $this->assertStringNotContainsString('onmouseover', $post->body);
        $this->assertStringNotContainsString('javascript:', $post->body);
        $this->assertStringContainsString('<strong>du</strong>', $post->body);
        $this->assertStringContainsString('https://example.test/x', $post->body);

        $this->expectException(CommunityException::class);
        $this->publish(body: '<script>alert(1)</script>');
    }

    public function test_one_reaction_per_account_and_target(): void
    {
        $post = $this->publish();
        $reactions = app(ReactionService::class);

        $reactions->react($post, $this->newcomer, 'like');
        $reactions->react($post, $this->newcomer, 'thanks');

        $this->assertSame(1, Reaction::count());
        $this->assertSame('thanks', Reaction::first()->kind);
    }

    public function test_comments_are_one_level_deep_and_fire_event(): void
    {
        Event::fake([CommentCreated::class]);
        $post = $this->publish();
        $comments = app(CommentService::class);

        $top = $comments->create($post, $this->newcomer, 'Oben');
        $reply = $comments->create($post, $this->singer, 'Antwort', $top);
        $deep = $comments->create($post, $this->newcomer, 'Tiefer', $reply);

        $this->assertSame($top->id, $reply->parent_id);
        $this->assertSame($top->id, $deep->parent_id);
        Event::assertDispatchedTimes(CommentCreated::class, 3);
    }

    public function test_edit_and_delete_rights(): void
    {
        $post = $this->publish();

        try {
            $this->posts()->update($post, $this->newcomer, '<p>fremd</p>');
            $this->fail('Others cannot edit.');
        } catch (AuthorizationException) {
        }
        try {
            $this->posts()->update($post, $this->moderator, '<p>auch nicht</p>');
            $this->fail('Moderators hide and delete, they do not rewrite.');
        } catch (AuthorizationException) {
        }
        try {
            $this->posts()->delete($post, $this->newcomer);
            $this->fail('Others cannot delete.');
        } catch (AuthorizationException) {
        }

        $this->posts()->update($post, $this->singer, '<p>neu</p>');
        $this->assertStringContainsString('neu', $post->fresh()->body);

        $this->posts()->delete($post, $this->moderator);
        $this->assertSame(0, Post::count());
    }

    public function test_account_erasure_removes_content_and_what_hangs_on_it(): void
    {
        $this->assertInstanceOf(CommunityContributor::class, app(ErasureRegistry::class)->available()['community']);
        $comments = app(CommentService::class);
        $reactions = app(ReactionService::class);

        $mine = $this->publish();
        $theirs = $this->publish($this->newcomer);
        $others = $comments->create($mine, $this->newcomer, 'Unter meinem Post');
        $myComment = $comments->create($theirs, $this->singer, 'Mein Kommentar');
        $reply = $comments->create($theirs, $this->newcomer, 'Antwort auf mich', $myComment);
        $reactions->react($mine, $this->newcomer);
        $reactions->react($theirs, $this->singer);
        $reactions->react($reply, $this->singer);
        $reactions->react($theirs, $this->moderator);

        $result = app(PersonalDataErasure::class)->erase($this->singer)['community'];

        $this->assertSame(1, $result->deleted['posts']);
        $this->assertNull(Post::find($mine->id));
        $this->assertNull(Comment::find($others->id));
        $this->assertNull(Comment::find($myComment->id));
        $this->assertNull(Comment::find($reply->id));
        $this->assertNotNull(Post::find($theirs->id));
        // What survives: the other person's post and the third party's reaction to it.
        $this->assertSame([$theirs->id], Reaction::pluck('target_id')->all());
        $this->assertSame(0, Reaction::where('user_id', $this->singer->id())->count());
    }

    public function test_export_lists_own_posts(): void
    {
        $this->publish();

        $export = app(PersonalDataExport::class);
        $mine = $export->collect($this->singer);
        $this->assertSame([], $mine['manifest']['failed']);
        $this->assertCount(1, $mine['sections']['community']['posts']);
        $this->assertCount(0, $export->collect($this->newcomer)['sections']['community']['posts']);
    }
}
