<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Badge, Listing, EmptyStateMenu, EmptyStateItem, CommandPaletteItem, Icon } from '@statamic/cms/ui';

/**
 * The posts listing.
 *
 * Core's <Listing> rather than a table of its own: search, filters, sorting,
 * column choice, saved views, paging and bulk actions all come with it. No
 * rows are passed in; the Listing fetches them from `listingUrl`, like core's
 * own listings. Without `actionUrl` (a user who may only view) it shows no
 * checkboxes and no row menu.
 */
defineProps({
    listingUrl: { type: String, required: true },
    actionUrl: { type: String, default: null },
    filters: { type: Array, default: () => [] },
    sortColumn: { type: String, default: 'created_at' },
    sortDirection: { type: String, default: 'desc' },
    // Whether any post exists at all, which is a different question from
    // whether this search found one.
    hasAny: { type: Boolean, default: false },
    roomsUrl: { type: String, required: true },
});
</script>

<template>
    <Head :title="__('Posts')" />

    <div class="max-w-page mx-auto" data-max-width-wrapper>
        <template v-if="!hasAny">
            <header class="py-8 pt-16 text-center">
                <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                    <Icon name="media-news-paper" class="size-5 text-gray-500" />{{ __('Posts') }}
                </h1>
            </header>
            <EmptyStateMenu :heading="__('Nothing has been posted yet. Posts appear here as soon as members write them in a room.')">
                <EmptyStateItem
                    :href="roomsUrl"
                    icon="groups"
                    :heading="__('Rooms')"
                    :description="__('The places members post in')"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('Posts')" icon="media-news-paper" />

            <CommandPaletteItem
                :text="[__('Community'), __('Posts')]"
                :url="listingUrl"
                icon="media-news-paper"
                prioritize
            />

            <Listing
                :url="listingUrl"
                :filters="filters"
                :sort-column="sortColumn"
                :sort-direction="sortDirection"
                :action-url="actionUrl"
                :action-context="{ type: 'post' }"
                preferences-prefix="community.posts"
                push-query
            >
                <template #cell-title="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium">{{ row.title }}</span>
                        <span v-if="row.excerpt && row.excerpt !== row.title" class="text-sm text-gray-600 dark:text-gray-400">{{ row.excerpt }}</span>
                        <span v-if="row.hidden && row.hidden_reason" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Hidden') }}: {{ row.hidden_reason }}
                        </span>
                    </div>
                </template>

                <template #cell-room="{ row }">
                    <span v-if="row.room">{{ row.room }}</span>
                    <span v-else class="text-gray-500 dark:text-gray-400">&mdash;</span>
                </template>

                <template #cell-region="{ row }">
                    <span v-if="row.region">{{ row.region }}</span>
                    <span v-else class="text-gray-500 dark:text-gray-400">&mdash;</span>
                </template>

                <template #cell-author="{ row }">
                    <span class="inline-flex items-center gap-2">
                        {{ row.author }}
                        <Badge v-if="row.author_type === 'team'" pill :text="__('Team')" />
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <div class="flex flex-wrap items-center gap-1">
                        <Badge v-if="row.status === 'pending'" pill color="amber" :text="__('Pending')" />
                        <Badge v-else pill color="green" :text="__('Published')" />
                        <Badge v-if="row.hidden" pill color="red" :text="__('Hidden')" />
                    </div>
                </template>

                <template #cell-comments="{ row }">
                    <span class="tabular-nums">{{ row.comments }}</span>
                </template>

                <template #cell-published_at="{ row }">
                    <date-time v-if="row.published_at" :of="row.published_at" />
                    <span v-else class="text-gray-500 dark:text-gray-400">&mdash;</span>
                </template>

                <template #cell-created_at="{ row }">
                    <date-time :of="row.created_at" />
                </template>
            </Listing>
        </template>
    </div>
</template>
