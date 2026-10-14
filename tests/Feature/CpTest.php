<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Community\Events\PostCreated;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Room;
use Goldnead\Community\Services\CommentService;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Services\RoomService;
use Goldnead\Community\Support\Stats;
use Goldnead\Community\Tests\TestCase;
use Goldnead\Community\Widgets\CommunityWidget;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Auth\Permissions;
use Statamic\Facades\User;

class CpTest extends TestCase
{
    private $author;

    private Room $choir;

    private Room $style;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = $this->makeUser('author@example.test', 'Anna Alt');
        $this->author->set('email_verified_at', now()->toIso8601String())->save();

        $this->choir = app(RoomService::class)->save(new Room, ['name' => 'Chor', 'kind' => 'topic']);
        $this->style = app(RoomService::class)->save(new Room, ['name' => 'Jazz', 'kind' => 'style']);
    }

    /** @param  list<string>  $permissions */
    private function cp(array $permissions = []): static
    {
        $allowed = array_merge(['access cp'], $permissions);
        Gate::before(fn ($user, $ability) => in_array($ability, $allowed, true) ? true : null);

        return $this->actingAs($this->makeUser('cp-'.uniqid().'@example.test', 'Moderator'));
    }

    private function viewer(): static
    {
        return $this->cp(['view community']);
    }

    private function moderator(): static
    {
        return $this->cp(['view community', 'moderate community']);
    }

    private function makePost(string $body = '<p>Hallo Chor</p>', ?Room $room = null, string $region = '', string $title = '', $team = null): Post
    {
        return app(PostService::class)->create($this->author, $body, $room, region: $region, title: $title, team: $team);
    }

    private function confirmedUser(string $email)
    {
        $user = $this->makeUser($email);
        $user->set('email_verified_at', now()->toIso8601String())->save();

        return $user;
    }

    private function filters(array $filters): string
    {
        return base64_encode(json_encode($filters));
    }

    /** @return list<int> */
    private function ids(string $uri, array $query = []): array
    {
        return collect($this->viewer()->getJson($uri.'?'.http_build_query($query))->assertOk()->json('data'))->pluck('id')->all();
    }

    // Permissions

    /** @return array<string, array{string, string}> */
    public static function pages(): array
    {
        return [
            'posts' => ['get', '/cp/community/posts'],
            'comments' => ['get', '/cp/community/comments'],
            'rooms' => ['get', '/cp/community/rooms'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function moderatorRoutes(): array
    {
        return [
            'create form' => ['get', '/cp/community/rooms/create'],
            'edit form' => ['get', '/cp/community/rooms/{room}/edit'],
            'store' => ['post', '/cp/community/rooms'],
            'update' => ['patch', '/cp/community/rooms/{room}'],
            'actions' => ['post', '/cp/community/actions'],
            'actions list' => ['post', '/cp/community/actions/list'],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function the_pages_need_view_community(string $method, string $uri): void
    {
        $this->cp()->{$method.'Json'}($uri)->assertForbidden();
        $this->viewer()->{$method.'Json'}($uri)->assertOk();
    }

    #[Test]
    #[DataProvider('moderatorRoutes')]
    public function every_changing_route_needs_moderate_community(string $method, string $uri): void
    {
        $uri = str_replace('{room}', (string) $this->choir->id, $uri);
        $body = ['name' => 'X', 'kind' => 'topic', 'action' => 'community_delete', 'selections' => [1], 'context' => ['type' => 'post']];

        $this->viewer()->{$method.'Json'}($uri, $body)->assertForbidden();
        $this->cp()->{$method.'Json'}($uri, $body)->assertForbidden();
        $this->assertSame('Chor', $this->choir->fresh()->name);
    }

    #[Test]
    public function the_permissions_are_registered_with_moderate_below_view(): void
    {
        $permissions = app(Permissions::class)->boot()->all();

        $this->assertTrue($permissions->has('view community'));
        $this->assertTrue($permissions->get('view community')->children()->contains(fn ($p) => $p->value() === 'moderate community'));
    }

    // Posts

    #[Test]
    public function the_posts_page_carries_filters_but_no_rows(): void
    {
        $this->makePost();

        $page = $this->moderator()->get('/cp/community/posts')->assertOk();
        $props = $page->viewData('page')['props'];

        $this->assertSame('community::Posts/Index', $page->viewData('page')['component']);
        $this->assertArrayNotHasKey('rows', $props);
        $this->assertNotEmpty($props['actionUrl']);
        $this->assertContains('community_room', collect($props['filters'])->pluck('handle')->all());
    }

    #[Test]
    public function viewers_get_no_action_url(): void
    {
        $props = $this->viewer()->get('/cp/community/posts')->viewData('page')['props'];

        $this->assertNull($props['actionUrl']);
    }

    #[Test]
    public function the_listing_never_exposes_an_address_or_an_account_id(): void
    {
        $this->makePost('<p>Geheim</p>', $this->choir);

        $response = $this->viewer()->getJson('/cp/community/posts')->assertOk();
        $row = $response->json('data.0');

        $this->assertSame('Anna Alt', $row['author']);
        $this->assertSame('person', $row['author_type']);
        $this->assertStringNotContainsString('author@example.test', $response->getContent());
        $this->assertStringNotContainsString((string) $this->author->id(), $response->getContent());
        $this->assertArrayNotHasKey('author_user_id', $row);
    }

    #[Test]
    public function an_author_without_a_name_is_not_named_by_the_address(): void
    {
        $nameless = $this->makeUser('nameless@example.test');
        $nameless->remove('name')->set('email_verified_at', now()->toIso8601String())->save();
        app(PostService::class)->create($nameless, '<p>Hi</p>');

        $response = $this->viewer()->getJson('/cp/community/posts')->assertOk();

        $this->assertSame('Member', $response->json('data.0.author'));
        $this->assertStringNotContainsString('nameless@example.test', $response->getContent());
    }

    #[Test]
    public function a_team_post_shows_the_team_name(): void
    {
        $team = $this->makeTeam($this->author);
        $this->makePost('<p>Wir proben</p>', null, team: $team);

        $row = $this->viewer()->getJson('/cp/community/posts')->json('data.0');

        $this->assertSame('Chor', $row['author']);
        $this->assertSame('team', $row['author_type']);
    }

    #[Test]
    public function posts_filter_by_room_region_status_hidden_and_author_type(): void
    {
        $mod = User::make()->email('boss@example.test')->data(['super' => true, 'name' => 'Boss', 'email_verified_at' => now()->toIso8601String()]);
        $mod->save();

        $inChoir = $this->makePost('<p>A</p>', $this->choir, 'Wien');
        $inStyle = app(PostService::class)->create($mod, '<p>B</p>', $this->style, region: 'Graz');
        config(['community.pre_moderation' => true]);
        $pending = app(PostService::class)->create($this->confirmedUser('fresh@example.test'), '<p>C</p>', $this->choir, region: 'Wien');
        config(['community.pre_moderation' => false]);
        $this->assertSame(Post::PENDING, $pending->status);
        app(PostService::class)->hide($inStyle, $mod, true, 'Spam');
        $team = $this->makeTeam($this->author);
        $byTeam = $this->makePost('<p>D</p>', $this->choir, 'Linz', team: $team);

        $filter = fn (array $f) => $this->ids('/cp/community/posts', ['filters' => $this->filters($f), 'sort' => 'created_at', 'order' => 'asc']);

        $this->assertEqualsCanonicalizing([$inChoir->id, $pending->id, $byTeam->id], $filter(['community_room' => ['room' => $this->choir->id]]));
        $this->assertEqualsCanonicalizing([$inChoir->id, $pending->id], $filter(['community_region' => ['region' => 'Wien']]));
        $this->assertSame([$pending->id], $filter(['community_status' => ['status' => 'pending']]));
        $this->assertSame([$inStyle->id], $filter(['community_hidden' => ['hidden' => 'yes']]));
        $this->assertSame([$byTeam->id], $filter(['community_author_type' => ['author_type' => 'team']]));
        $this->assertSame([$inChoir->id], $filter(['community_room' => ['room' => $this->choir->id], 'community_region' => ['region' => 'Wien'], 'community_status' => ['status' => 'published']]));
    }

    #[Test]
    public function an_unknown_filter_value_filters_nothing(): void
    {
        $this->makePost();
        $this->makePost('<p>Zwei</p>');

        $this->assertCount(2, $this->ids('/cp/community/posts', ['filters' => $this->filters(['community_status' => ['status' => 'bogus'], 'community_room' => ['room' => 'x']])]));
    }

    #[Test]
    public function posts_are_searched_by_text_with_wildcards_escaped(): void
    {
        $hit = $this->makePost('<p>Stimmbildung am Montag</p>');
        $this->makePost('<p>Ganz anderes</p>');
        $percent = $this->makePost('<p>Rabatt 50% heute</p>');

        $this->assertSame([$hit->id], $this->ids('/cp/community/posts', ['search' => 'stimmbild']));
        $this->assertSame([$percent->id], $this->ids('/cp/community/posts', ['search' => '50%']));
        $this->assertCount(0, $this->ids('/cp/community/posts', ['search' => 'author@example.test']));
    }

    #[Test]
    public function the_sort_column_is_a_positive_list(): void
    {
        $this->makePost();

        $this->viewer()->getJson('/cp/community/posts?sort=author_user_id&order=asc')->assertOk();
    }

    #[Test]
    public function the_columns_come_with_every_response(): void
    {
        $this->makePost();

        $columns = collect($this->viewer()->getJson('/cp/community/posts')->json('meta.columns'))->pluck('field')->all();

        $this->assertContains('title', $columns);
        $this->assertContains('status', $columns);
    }

    // Actions on posts

    private function act(string $action, array $ids, string $type = 'post', array $values = [])
    {
        return $this->moderator()->postJson('/cp/community/actions', ['action' => $action, 'selections' => $ids, 'context' => ['type' => $type], 'values' => $values]);
    }

    #[Test]
    public function approving_publishes_a_pending_post_once(): void
    {
        config(['community.pre_moderation' => true]);
        $post = $this->makePost();
        Event::fake([PostCreated::class]);

        $this->act('community_approve_post', [$post->id])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(Post::PUBLISHED, $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
        Event::assertDispatchedTimes(PostCreated::class, 1);
    }

    #[Test]
    public function the_approve_action_is_offered_for_pending_posts_only(): void
    {
        config(['community.pre_moderation' => true]);
        $pending = $this->makePost();
        config(['community.pre_moderation' => false]);
        $published = $this->makePost('<p>Zwei</p>');

        $offered = fn (Post $p) => collect($this->moderator()->postJson('/cp/community/actions/list', ['selections' => [$p->id], 'context' => ['type' => 'post']])->assertOk()->json())->pluck('handle')->all();

        $this->assertContains('community_approve_post', $offered($pending));
        $this->assertNotContains('community_approve_post', $offered($published));
        $this->assertContains('community_hide', $offered($published));
        $this->assertNotContains('community_unhide', $offered($published));
    }

    #[Test]
    public function hiding_stores_the_reason_and_showing_again_clears_it(): void
    {
        $post = $this->makePost();

        $this->act('community_hide', [$post->id], values: ['reason' => 'Werbung'])->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($post->fresh()->hidden);
        $this->assertSame('Werbung', $post->fresh()->hidden_reason);
        $this->assertFalse($post->fresh()->isVisible());

        $row = $this->viewer()->getJson('/cp/community/posts')->json('data.0');
        $this->assertTrue($row['hidden']);
        $this->assertSame('Werbung', $row['hidden_reason']);

        $this->act('community_unhide', [$post->id])->assertOk();
        $this->assertFalse($post->fresh()->hidden);
        $this->assertNull($post->fresh()->hidden_reason);
    }

    #[Test]
    public function deleting_removes_the_post_with_its_comments(): void
    {
        $post = $this->makePost();
        $this->choir->update(['pinned_post_id' => $post->id]);
        $comment = app(CommentService::class)->create($post, $this->author, '<p>Re</p>');

        $this->act('community_delete', [$post->id])->assertOk()->assertJsonPath('success', true);

        $this->assertNull(Post::find($post->id));
        $this->assertNull(Comment::find($comment->id));
        $this->assertNull($this->choir->fresh()->pinned_post_id);
    }

    #[Test]
    public function an_action_on_a_missing_id_is_a_404_not_a_shrunken_selection(): void
    {
        $post = $this->makePost();

        $this->act('community_delete', [$post->id, 9999])->assertNotFound();
        $this->assertNotNull(Post::find($post->id));
    }

    #[Test]
    public function an_action_the_user_may_not_run_changes_nothing(): void
    {
        $post = $this->makePost();

        // `view community` reaches the page but the route needs `moderate community`.
        $this->viewer()->postJson('/cp/community/actions', ['action' => 'community_hide', 'selections' => [$post->id], 'context' => ['type' => 'post']])->assertForbidden();
        $this->assertFalse($post->fresh()->hidden);
    }

    // Comments

    #[Test]
    public function comments_list_and_filter_through_their_post(): void
    {
        $inChoir = $this->makePost('<p>A</p>', $this->choir, 'Wien');
        $inStyle = $this->makePost('<p>B</p>', $this->style, 'Graz');
        $one = app(CommentService::class)->create($inChoir, $this->author, '<p>Erster Kommentar</p>');
        $two = app(CommentService::class)->create($inStyle, $this->author, '<p>Zweiter</p>');
        $reply = app(CommentService::class)->create($inStyle, $this->author, '<p>Antwort</p>', $two);

        $filter = fn (array $f) => $this->ids('/cp/community/comments', ['filters' => $this->filters($f)]);

        $this->assertCount(3, $this->ids('/cp/community/comments'));
        $this->assertSame([$one->id], $filter(['community_room' => ['room' => $this->choir->id]]));
        $this->assertEqualsCanonicalizing([$two->id, $reply->id], $filter(['community_region' => ['region' => 'Graz']]));
        $this->assertSame([$two->id], $this->ids('/cp/community/comments', ['search' => 'zweiter']));

        $row = collect($this->viewer()->getJson('/cp/community/comments')->json('data'))->firstWhere('id', $reply->id);
        $this->assertTrue($row['is_reply']);
        $this->assertSame('Anna Alt', $row['author']);
    }

    #[Test]
    public function comments_filter_by_visibility_and_author_type(): void
    {
        $post = $this->makePost();
        $team = $this->makeTeam($this->author);
        $person = app(CommentService::class)->create($post, $this->author, '<p>Person</p>');
        $byTeam = app(CommentService::class)->create($post, $this->author, '<p>Team</p>', team: $team);

        $this->assertSame([$byTeam->id], $this->ids('/cp/community/comments', ['filters' => $this->filters(['community_author_type' => ['author_type' => 'team']])]));

        $this->act('community_hide', [$person->id], 'comment', ['reason' => 'Off topic'])->assertOk();
        $this->assertTrue($person->fresh()->hidden);
        $this->assertSame([$person->id], $this->ids('/cp/community/comments', ['filters' => $this->filters(['community_hidden' => ['hidden' => 'yes']])]));

        $this->act('community_unhide', [$person->id], 'comment')->assertOk();
        $this->assertFalse($person->fresh()->hidden);
    }

    #[Test]
    public function a_comment_can_be_deleted_with_its_replies(): void
    {
        $post = $this->makePost();
        $top = app(CommentService::class)->create($post, $this->author, '<p>Oben</p>');
        $reply = app(CommentService::class)->create($post, $this->author, '<p>Unten</p>', $top);

        $this->act('community_delete', [$top->id], 'comment')->assertOk()->assertJsonPath('success', true);

        $this->assertNull(Comment::find($top->id));
        $this->assertNull(Comment::find($reply->id));
    }

    #[Test]
    public function comment_ids_are_not_read_as_post_ids(): void
    {
        $post = $this->makePost();
        $comment = app(CommentService::class)->create($post, $this->author, '<p>Re</p>');

        $this->act('community_hide', [$comment->id], 'comment')->assertOk();

        $this->assertTrue($comment->fresh()->hidden);
        $this->assertFalse($post->fresh()->hidden);
    }

    // Rooms

    #[Test]
    public function the_rooms_list_counts_members_and_posts(): void
    {
        app(RoomService::class)->join($this->choir, $this->author);
        $this->makePost('<p>A</p>', $this->choir);

        $page = $this->viewer()->get('/cp/community/rooms')->assertOk()->viewData('page');
        $row = collect($page['props']['rooms'])->firstWhere('id', $this->choir->id);

        $this->assertSame('community::Rooms/Index', $page['component']);
        $this->assertSame(1, $row['members']);
        $this->assertSame(1, $row['posts']);
        $this->assertFalse($page['props']['canManage']);
    }

    #[Test]
    public function a_room_is_created_with_a_valid_kind(): void
    {
        $response = $this->moderator()->postJson('/cp/community/rooms', ['name' => 'Gospel Fans', 'kind' => 'style', 'icon' => '🎶', 'description' => 'Alles Gospel'])
            ->assertOk();

        $room = Room::where('slug', 'gospel-fans')->firstOrFail();
        $response->assertJsonPath('redirect', url('/cp/community/rooms/'.$room->id.'/edit'));
        $this->assertSame('style', $room->kind);
        $this->assertTrue($room->curated);
    }

    #[Test]
    public function a_room_with_an_unknown_kind_is_refused(): void
    {
        $this->moderator()->postJson('/cp/community/rooms', ['name' => 'X', 'kind' => 'galaxy'])
            ->assertUnprocessable()->assertJsonValidationErrors('kind');

        $this->assertSame(2, Room::count());
    }

    #[Test]
    public function a_taken_slug_is_refused(): void
    {
        $this->moderator()->postJson('/cp/community/rooms', ['name' => 'Noch ein Chor', 'slug' => 'chor', 'kind' => 'topic'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    #[Test]
    public function a_room_is_edited_but_its_slug_stays(): void
    {
        $this->moderator()->patchJson('/cp/community/rooms/'.$this->choir->id, ['name' => 'Chorraum', 'kind' => 'region', 'description' => 'Neu'])->assertOk();

        $room = $this->choir->fresh();
        $this->assertSame('Chorraum', $room->name);
        $this->assertSame('region', $room->kind);
        $this->assertSame('chor', $room->slug);

        $this->moderator()->patchJson('/cp/community/rooms/'.$this->choir->id, ['name' => 'Chorraum', 'kind' => 'region', 'slug' => 'anders'])->assertOk();
        $this->assertSame('chor', $this->choir->fresh()->slug);
    }

    #[Test]
    public function a_published_post_of_the_room_can_be_pinned_and_leads_its_feed(): void
    {
        $old = $this->makePost('<p>Alt</p>', $this->choir);
        $this->makePost('<p>Neu</p>', $this->choir);

        $this->moderator()->patchJson('/cp/community/rooms/'.$this->choir->id, ['name' => 'Chor', 'kind' => 'topic', 'pinned_post_id' => $old->id])->assertOk();

        $this->assertSame($old->id, $this->choir->fresh()->pinned_post_id);
        $this->assertSame($old->id, app(PostService::class)->feed($this->choir->fresh())->first()->id);

        $this->moderator()->patchJson('/cp/community/rooms/'.$this->choir->id, ['name' => 'Chor', 'kind' => 'topic', 'pinned_post_id' => null])->assertOk();
        $this->assertNull($this->choir->fresh()->pinned_post_id);
    }

    #[Test]
    public function a_post_of_another_room_cannot_be_pinned(): void
    {
        $elsewhere = $this->makePost('<p>Anderswo</p>', $this->style);

        $this->moderator()->patchJson('/cp/community/rooms/'.$this->choir->id, ['name' => 'Chor', 'kind' => 'topic', 'pinned_post_id' => $elsewhere->id])
            ->assertUnprocessable()->assertJsonValidationErrors('pinned_post_id');

        $this->assertNull($this->choir->fresh()->pinned_post_id);
    }

    #[Test]
    public function a_pending_post_cannot_be_pinned(): void
    {
        config(['community.pre_moderation' => true]);
        $pending = $this->makePost('<p>Wartet</p>', $this->choir);

        $this->moderator()->patchJson('/cp/community/rooms/'.$this->choir->id, ['name' => 'Chor', 'kind' => 'topic', 'pinned_post_id' => $pending->id])
            ->assertUnprocessable()->assertJsonValidationErrors('pinned_post_id');

        $this->assertNull($this->choir->fresh()->pinned_post_id);
    }

    #[Test]
    public function the_edit_form_offers_the_posts_of_the_room_to_pin(): void
    {
        $mine = $this->makePost('<p>Meins</p>', $this->choir, title: 'Probenplan');
        $this->makePost('<p>Anderes</p>', $this->style);

        $page = $this->moderator()->get('/cp/community/rooms/'.$this->choir->id.'/edit')->assertOk()->viewData('page');
        $blueprint = json_encode($page['props']['blueprint']);

        $this->assertSame('PublishForm', $page['component']);
        $this->assertStringContainsString('Probenplan', $blueprint);
        $this->assertStringNotContainsString('Anderes', $blueprint);
        $this->assertStringContainsString('pinned_post_id', $blueprint);
    }

    // Widget

    #[Test]
    public function the_widget_counts_the_last_seven_days_pending_and_hidden(): void
    {
        config(['community.pre_moderation' => true]);
        $pending = $this->makePost('<p>Wartet</p>');
        config(['community.pre_moderation' => false]);
        $recent = $this->makePost('<p>Neu</p>');
        $old = $this->makePost('<p>Alt</p>');
        Post::whereKey($old->id)->update(['created_at' => now()->subDays(10)]);
        $comment = app(CommentService::class)->create($recent, $this->author, '<p>Re</p>');
        $oldComment = app(CommentService::class)->create($recent, $this->author, '<p>Alt</p>');
        Comment::whereKey($oldComment->id)->update(['created_at' => now()->subDays(8)]);
        app(PostService::class)->hide($recent, User::make()->email('s@example.test')->data(['super' => true]), true);
        Comment::whereKey($comment->id)->update(['hidden' => true]);

        $this->assertSame(['posts' => 2, 'comments' => 1, 'pending' => 1, 'hidden' => 2], Stats::summary());

        $this->actingAs($this->makeUser('w@example.test'));
        Gate::before(fn ($user, $ability) => in_array($ability, ['view community', 'access cp'], true) ? true : null);
        $component = (new CommunityWidget)->component()->toArray();

        $this->assertSame('community-widget', $component['name']);
        $this->assertSame(Stats::summary(), $component['props']['stats']);
        $this->assertNotNull($pending);
    }

    #[Test]
    public function the_widget_is_empty_without_view_community(): void
    {
        $this->actingAs($this->makeUser('w@example.test'));

        $this->assertNull((new CommunityWidget)->component());
    }
}
