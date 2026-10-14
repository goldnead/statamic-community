<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            // The public feed: `status = published and hidden = 0 order by published_at`.
            $table->index(['status', 'hidden', 'published_at'], 'community_posts_feed_index');
            $table->string('hidden_reason')->nullable()->after('hidden');
        });

        Schema::table('community_comments', function (Blueprint $table) {
            $table->string('hidden_reason')->nullable()->after('hidden');
        });

        Schema::table('community_rooms', function (Blueprint $table) {
            // No foreign key: a post is pinned in the room it belongs to, and
            // deleting it clears the pin in the service, not in the schema.
            $table->unsignedBigInteger('pinned_post_id')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('community_rooms', fn (Blueprint $table) => $table->dropColumn('pinned_post_id'));
        Schema::table('community_comments', fn (Blueprint $table) => $table->dropColumn('hidden_reason'));
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex('community_posts_feed_index');
            $table->dropColumn('hidden_reason');
        });
    }
};
