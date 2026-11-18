<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Community\Models\Room;
use Goldnead\Community\Services\RoomService;
use Goldnead\Community\Support\Setup;
use Goldnead\Community\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * The room edit page's setup guard comes from `goldnead/statamic-brand-context`
 * (>= 1.27), which is a suggest. Both installs have to hold:
 *
 * - with the package, the edit page on an unmigrated database shows the same
 *   setup screen the listing shows, not an HTTP 500;
 * - without it, the route carries no guard middleware and nothing else changes.
 *
 * CI runs the suite twice, once with the package in vendor and once with it
 * removed (`composer remove --dev`). A test that needs the other install skips
 * itself, so each leg proves its own half.
 */
class BrandContextGuardTest extends TestCase
{
    private Room $choir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->choir = app(RoomService::class)->save(new Room, ['name' => 'Chor', 'kind' => 'topic']);
    }

    /** The same gate CpTest uses: view and moderate community, granted through Gate::before. */
    private function moderator(): static
    {
        $allowed = ['access cp', 'view community', 'moderate community'];
        Gate::before(fn ($user, $ability) => in_array($ability, $allowed, true) ? true : null);

        return $this->actingAs($this->makeUser('mod-'.uniqid().'@example.test', 'Moderator'));
    }

    private function installed(): bool
    {
        return class_exists(self::BRAND_GUARD);
    }

    /** Children first: reactions and comments point at posts, posts and memberships at rooms. */
    private function dropCommunityTables(): void
    {
        foreach (['community_reactions', 'community_comments', 'community_posts', 'community_room_memberships', 'community_rooms'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function editMiddleware(): array
    {
        return Route::getRoutes()->getByName('statamic.cp.community.rooms.edit')->gatherMiddleware();
    }

    #[Test]
    public function a_missing_middleware_class_gives_no_middleware(): void
    {
        $this->assertSame([], Setup::detailGuardFrom('Goldnead\NotInstalled\GuardSetup', 'Rooms', 'community_rooms'));
    }

    #[Test]
    public function without_brand_context_the_edit_route_carries_no_guard(): void
    {
        if ($this->installed()) {
            $this->markTestSkipped('goldnead/statamic-brand-context is installed.');
        }

        $guards = array_filter($this->editMiddleware(), fn ($middleware) => str_starts_with((string) $middleware, 'brand.setup-guard'));

        $this->assertSame([], $guards);
    }

    #[Test]
    public function with_brand_context_the_edit_route_asks_this_addons_setup_for_the_tables_it_reads(): void
    {
        if (! $this->installed()) {
            $this->markTestSkipped('goldnead/statamic-brand-context is not installed.');
        }

        $this->assertSame(
            ['brand.setup-guard:'.Setup::class.',Rooms,community_rooms,community_posts'],
            Setup::detailGuard('Rooms', 'community_rooms', 'community_posts'),
        );
        $this->assertContains('brand.setup-guard:'.Setup::class.',Rooms,community_rooms,community_posts', $this->editMiddleware());
    }

    #[Test]
    public function with_brand_context_the_edit_page_shows_the_setup_screen_when_tables_are_missing(): void
    {
        if (! $this->installed()) {
            $this->markTestSkipped('goldnead/statamic-brand-context is not installed.');
        }

        $room = $this->choir;
        $this->dropCommunityTables();

        $page = $this->moderator()
            ->get('/cp/community/rooms/'.$room->id.'/edit')
            ->assertOk()
            ->viewData('page');

        $this->assertSame('community::SetupRequired', $page['component']);
        $this->assertContains('community_rooms', $page['props']['tables']);
    }

    #[Test]
    public function with_brand_context_a_migrated_install_still_shows_the_edit_form(): void
    {
        if (! $this->installed()) {
            $this->markTestSkipped('goldnead/statamic-brand-context is not installed.');
        }

        $page = $this->moderator()
            ->get('/cp/community/rooms/'.$this->choir->id.'/edit')
            ->assertOk()
            ->viewData('page');

        $this->assertSame('PublishForm', $page['component']);
    }

    #[Test]
    public function the_create_form_reads_no_table_so_it_needs_no_guard(): void
    {
        $this->dropCommunityTables();

        $this->moderator()->get('/cp/community/rooms/create')->assertOk();
    }

    #[Test]
    public function the_setup_guard_checks_the_tables_it_is_given_and_all_of_them_when_given_none(): void
    {
        Schema::dropIfExists('community_comments');

        // Only the tables named are checked: comments are missing, but this page does not read them.
        $this->assertNull(Setup::guard('Rooms', 'community_rooms', 'community_posts'));

        // None named: the addon's whole list, as the listings have always had it.
        $this->assertNotNull(Setup::guard('Rooms'));
    }
}
