<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Community\Events\PostCreated;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * Moderation has to hold: a moderator's "hidden" is not undone by an approval,
 * a hidden post does not make its author "trusted", and an author who lost the
 * right to speak for a team cannot keep editing the team's posts.
 */
class ModerationBypassTest extends TestCase
{
    private function confirmed(string $email)
    {
        $user = $this->makeUser($email);
        $user->set('email_verified_at', now()->toIso8601String())->save();

        return $user;
    }

    private function moderator()
    {
        $moderator = $this->confirmed('mod@example.test');
        $moderator->set('super', true)->save();

        return $moderator;
    }

    public function test_approving_a_hidden_pending_post_neither_publishes_nor_announces_it(): void
    {
        config(['community.pre_moderation' => true]);
        $author = $this->confirmed('author@example.test');
        $moderator = $this->moderator();
        $post = app(PostService::class)->create($author, '<p>Wartet</p>');
        app(PostService::class)->hide($post, $moderator, true, 'Spam');

        Event::fake([PostCreated::class]);
        app(PostService::class)->approve($post->fresh(), $moderator);

        Event::assertNotDispatched(PostCreated::class);
        $this->assertSame(Post::PENDING, $post->fresh()->status);
    }

    public function test_a_hidden_post_does_not_count_towards_being_trusted(): void
    {
        config(['community.pre_moderation' => true, 'community.trusted_after' => 1]);
        $author = $this->confirmed('author@example.test');
        $moderator = $this->moderator();
        $posts = app(PostService::class);

        $first = $posts->create($author, '<p>Erster</p>');
        $posts->approve($first, $moderator);
        $posts->hide($first->fresh(), $moderator, true, 'Spam');

        // The only published post was moderated away: the author waits again.
        $this->assertSame(Post::PENDING, $posts->create($author, '<p>Zweiter</p>')->status);
    }

    public function test_an_author_cannot_edit_a_post_that_moderation_hid(): void
    {
        $author = $this->confirmed('author@example.test');
        $post = app(PostService::class)->create($author, '<p>Hallo</p>');
        app(PostService::class)->hide($post, $this->moderator(), true, 'Spam');

        $this->assertFalse(Gate::forUser($author)->allows('update', $post->fresh()));
    }

    public function test_a_member_who_lost_the_team_permission_cannot_edit_or_delete_the_teams_posts(): void
    {
        $admin = $this->confirmed('admin@example.test');
        $member = $this->confirmed('member@example.test');
        $team = $this->makeTeam($admin, $member);

        // The member wrote for the team while allowed to; the right was withdrawn since.
        $post = Post::create([
            'author_user_id' => $member->id(),
            'author_team_id' => $team->id,
            'category' => 'community',
            'body' => '<p>Im Namen des Chors</p>',
            'status' => Post::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($member)->allows('update', $post));
        $this->assertFalse(Gate::forUser($member)->allows('delete', $post));

        // The one who may speak for the team still edits and deletes what they wrote for it.
        $own = Post::create([
            'author_user_id' => $admin->id(),
            'author_team_id' => $team->id,
            'category' => 'community',
            'body' => '<p>Wir suchen Tenöre</p>',
            'status' => Post::PUBLISHED,
            'published_at' => now(),
        ]);
        $this->assertTrue(Gate::forUser($admin)->allows('update', $own));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $own));
    }
}
