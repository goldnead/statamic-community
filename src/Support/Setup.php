<?php

namespace Goldnead\Community\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The check a CP page runs before its first query.
 *
 * When the migrations have not run, the page shows a sentence instead of a
 * 500, and the reason goes to the log as well: an empty page that says
 * nothing anywhere would look installed and never work.
 */
final class Setup
{
    public const TABLES = ['community_rooms', 'community_room_memberships', 'community_posts', 'community_comments', 'community_reactions'];

    /**
     * The class name of `goldnead/statamic-brand-context` (>= 1.27)'s route middleware for CP detail
     * pages. A string, never an import: the package is a suggest, and a `use` of a missing class is a
     * fatal on the first page.
     */
    private const DETAIL_GUARD = 'Goldnead\BrandContext\Http\Middleware\GuardSetup';

    /**
     * Route middleware that shows this addon's setup screen on a CP detail page whose tables are
     * missing. Nothing when brand-context (>= 1.27) is not installed: the page behaves as it always did.
     *
     * @return list<string>
     */
    public static function detailGuard(string $title, string ...$tables): array
    {
        return self::detailGuardFrom(self::DETAIL_GUARD, $title, ...$tables);
    }

    /**
     * {@see detailGuard()} with the middleware class named, so a test can ask what happens when it is
     * not there. Not meant for anything else.
     *
     * @internal
     *
     * @param  class-string|string  $guard
     * @return list<string>
     */
    public static function detailGuardFrom(string $guard, string $title, string ...$tables): array
    {
        if (! class_exists($guard) || ! method_exists($guard, 'using')) {
            return [];
        }

        return [$guard::using(self::class, $title, ...$tables)];
    }

    /**
     * @param  string  $title  The page's own heading.
     * @param  string  ...$tables  Every table the page reads while it renders. None given: all of {@see TABLES}.
     */
    public static function guard(string $title, string ...$tables): ?Response
    {
        $tables = $tables === [] ? self::TABLES : $tables;

        $missing = array_values(array_filter($tables, fn (string $table) => ! Schema::hasTable($table)));

        if ($missing === []) {
            return null;
        }

        Log::error('statamic-community: the CP page "'.$title.'" cannot load, these tables are missing: '.implode(', ', $missing).'. Run `php artisan migrate`.');

        return Inertia::render('community::SetupRequired', [
            'title' => $title,
            'heading' => __('The database tables for the community are missing.'),
            'description' => __('Run php artisan migrate on the server.'),
            'tables' => $missing,
        ]);
    }
}
