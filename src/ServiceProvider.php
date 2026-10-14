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
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    /**
     * Must byte-match `laravel()` in vite.config.js.
     */
    protected $vite = [
        'hotFile' => __DIR__.'/../dist/hot',
        'publicDirectory' => 'dist',
        'input' => ['resources/js/cp.js', 'resources/css/cp.css'],
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/community.php', 'community');

        // The Control Panel is written in English; `lang/de.json` translates it.
        // Registered here, before the translator first loads a locale: a path
        // added from `bootAddon()` comes after the JSON files were read and is
        // ignored for the rest of the request.
        $langPath = __DIR__.'/../lang';
        $this->app->resolving('translator', fn ($translator) => $translator->addJsonPath($langPath));

        if ($this->app->resolved('translator')) {
            $this->app['translator']->addJsonPath($langPath);
        }

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

        $this->bootPermissions();
        $this->bootNav();
        app(TeamsManager::class)->registerPermission(CommunityPolicy::TEAM_PERMISSION, 'Post as team');

        // goldnead/statamic-accounts picks these up by tag; `key()` is the
        // same, so one class serves the export and the erasure.
        if (interface_exists('Goldnead\Accounts\Contracts\ErasesPersonalData')) {
            $this->app->tag([CommunityContributor::class], 'accounts.personal-data');
            $this->app->tag([CommunityContributor::class], 'accounts.personal-data-erasers');
        }
    }

    protected function bootPermissions(): void
    {
        Permission::extend(function () {
            Permission::group('community', __('Community'), function () {
                Permission::register('view community')
                    ->label(__('View community'))
                    ->children([
                        Permission::make(CommunityPolicy::MODERATE)
                            ->label(__('Moderate community')),
                    ]);
            });
        });
    }

    protected function bootNav(): void
    {
        Nav::extend(function ($nav) {
            $nav->create(__('Posts'))->section(__('Community'))->icon('media-news-paper')->route('community.posts.index')->can('view community');
            $nav->create(__('Comments'))->section(__('Community'))->icon('mail-chat-bubble-text')->route('community.comments.index')->can('view community');
            $nav->create(__('Rooms'))->section(__('Community'))->icon('groups')->route('community.rooms.index')->can('view community');
        });
    }
}
