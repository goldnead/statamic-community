<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Services\CommentService;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Support\Sanitizer;
use Goldnead\Community\Support\Setup;
use Goldnead\Community\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;

/** A guard class with an alias nobody registered, as a brand-context install without its provider looks. */
class StilleFehlerUnregistriertesGuard
{
    public const ALIAS = 'brand.stille-fehler-nie-registriert';

    public static function using(string $setup, string $title, string ...$tables): string
    {
        return self::ALIAS.':'.$setup;
    }
}

class StilleFehlerTest extends TestCase
{
    private function moderator(): static
    {
        $allowed = ['access cp', 'view community', 'moderate community'];
        Gate::before(fn ($user, $ability) => in_array($ability, $allowed, true) ? true : null);

        return $this->actingAs($this->makeUser('stille-mod-'.uniqid().'@example.test', 'Moderator'));
    }

    public function test_a_link_filter_failure_is_logged(): void
    {
        Log::spy();
        $previous = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');

        try {
            $method = new ReflectionMethod(Sanitizer::class, 'stripUnsafeLinks');
            $method->setAccessible(true);
            $method->invoke(new Sanitizer, '<a href="https://example.test">x</a>');
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previous);
        }

        Log::shouldHaveReceived('error')->once();
    }

    /** Post and comment rights are not what these tests are about, so every gate passes. */
    private function alleRechte(): void
    {
        Gate::before(fn () => true);
    }

    public function test_a_body_of_only_a_non_breaking_space_is_empty(): void
    {
        $this->alleRechte();
        $user = $this->makeUser('stille-nbsp-post@example.test');

        $this->expectException(CommunityException::class);

        app(PostService::class)->create($user, '<p>&#160;</p>');
    }

    public function test_a_comment_of_only_a_non_breaking_space_is_empty(): void
    {
        $this->alleRechte();
        $author = $this->makeUser('stille-nbsp-autor@example.test');
        $post = app(PostService::class)->create($author, '<p>Hallo Chor</p>');
        $user = $this->makeUser('stille-nbsp-kommentar@example.test');

        $this->expectException(CommunityException::class);

        app(CommentService::class)->create($post, $user, '<p>&#160;</p>');
    }

    public function test_a_moderation_action_with_an_unknown_context_type_is_refused(): void
    {
        $this->alleRechte();
        $post = app(PostService::class)->create($this->makeUser('stille-ctx-autor@example.test'), '<p>Hallo Chor</p>');

        $this->moderator()
            ->postJson('/cp/community/actions', ['action' => 'community_hide', 'selections' => [$post->id], 'context' => ['type' => 'bogus'], 'values' => []])
            ->assertStatus(422);

        $this->assertFalse(Post::find($post->id)->hidden);
    }

    public function test_a_guard_with_an_unregistered_alias_is_logged(): void
    {
        Log::spy();

        $this->assertSame([], Setup::detailGuardFrom(StilleFehlerUnregistriertesGuard::class, 'Titel'));

        Log::shouldHaveReceived('warning')->once();
    }
}
