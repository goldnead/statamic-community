/**
 * Control Panel entry. The registered names must match what the controllers
 * pass to `Inertia::render()` and what `CommunityWidget` passes to
 * `VueComponent::render()`, exactly: a mismatch is a blank screen with
 * nothing in the log.
 *
 * The room form has no page of its own: it is core's `PublishForm`.
 */

import PostsIndex from './pages/Posts/Index.vue';
import CommentsIndex from './pages/Comments/Index.vue';
import RoomsIndex from './pages/Rooms/Index.vue';
import SetupRequired from './pages/SetupRequired.vue';
import CommunityWidget from './components/CommunityWidget.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('community::Posts/Index', PostsIndex);
    Statamic.$inertia.register('community::Comments/Index', CommentsIndex);
    Statamic.$inertia.register('community::Rooms/Index', RoomsIndex);
    Statamic.$inertia.register('community::SetupRequired', SetupRequired);
    Statamic.$components.register('community-widget', CommunityWidget);
});
