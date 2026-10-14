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

    public static function guard(string $title): ?Response
    {
        $missing = array_values(array_filter(self::TABLES, fn (string $table) => ! Schema::hasTable($table)));

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
