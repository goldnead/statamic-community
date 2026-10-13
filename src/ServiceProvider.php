<?php

namespace Goldnead\Community;

use Goldnead\Community\Integrations\Accounts\CommunityContributor;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Policies\CommunityPolicy;
use Goldnead\Community\Services\CommentService;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Services\ReactionService;
use Goldnead\Community\Services\RoomService;
use Goldnead\Community\Support\Sanitizer;
use Goldnead\Teams\TeamsManager;
use Illuminate\Support\Facades\Gate;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/community.php', 'community');

        foreach ([Sanitizer::class, PostService::class, CommentService::class, ReactionService::class, RoomService::class] as $service) {
            $this->app->singleton($service);
        }
    }

    public function bootAddon(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../config/community.php' => config_path('community.php')], 'community-config');

        Gate::policy(Post::class, CommunityPolicy::class);
        Gate::policy(Comment::class, CommunityPolicy::class);

        Permission::register(CommunityPolicy::MODERATE)->label('Moderate community');
        app(TeamsManager::class)->registerPermission(CommunityPolicy::TEAM_PERMISSION, 'Post as team');

        // goldnead/statamic-accounts picks these up by tag; `key()` is the
        // same, so one class serves the export and the erasure.
        if (interface_exists('Goldnead\Accounts\Contracts\ErasesPersonalData')) {
            $this->app->tag([CommunityContributor::class], 'accounts.personal-data');
            $this->app->tag([CommunityContributor::class], 'accounts.personal-data-erasers');
        }
    }
}
