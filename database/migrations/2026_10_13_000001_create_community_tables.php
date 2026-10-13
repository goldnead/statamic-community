<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kind')->default('topic')->index(); // region|style|topic
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('curated')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('community_room_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('community_rooms')->cascadeOnDelete();
            $table->string('user_id')->index();
            $table->string('role')->default('member'); // member|moderator
            $table->string('notify')->default('digest'); // off|digest|instant
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
        });

        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->string('author_user_id')->index();
            $table->unsignedBigInteger('author_team_id')->nullable()->index(); // set when posted as a team
            $table->foreignId('room_id')->nullable()->constrained('community_rooms')->nullOnDelete();
            $table->string('category')->default('community');
            $table->string('region')->default('')->index();
            $table->string('title')->default('');
            $table->text('body');
            $table->string('status')->default('published'); // published|pending
            $table->boolean('hidden')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('community_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('community_comments')->cascadeOnDelete();
            $table->string('author_user_id')->index();
            $table->unsignedBigInteger('author_team_id')->nullable()->index();
            $table->text('body');
            $table->boolean('hidden')->default(false);
            $table->timestamps();
        });

        Schema::create('community_reactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->index();
            $table->string('target_type'); // post|comment
            $table->unsignedBigInteger('target_id');
            $table->string('kind')->default('like');
            $table->timestamps();

            $table->unique(['user_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reactions');
        Schema::dropIfExists('community_comments');
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_room_memberships');
        Schema::dropIfExists('community_rooms');
    }
};
