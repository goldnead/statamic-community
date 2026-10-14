<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Badge, Listing, EmptyStateMenu, EmptyStateItem, CommandPaletteItem, Icon } from '@statamic/cms/ui';

/**
 * The comments listing. The same shape as the posts listing: rows come from
 * `listingUrl`, filters and actions are core's.
 */
defineProps({
    listingUrl: { type: String, required: true },
    actionUrl: { type: String, default: null },
    filters: { type: Array, default: () => [] },
    sortColumn: { type: String, default: 'created_at' },
    sortDirection: { type: String, default: 'desc' },
    hasAny: { type: Boolean, default: false },
});
</script>

<template>
    <Head :title="__('Comments')" />

    <div class="max-w-page mx-auto" data-max-width-wrapper>
        <template v-if="!hasAny">
            <header class="py-8 pt-16 text-center">
                <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                    <Icon name="mail-chat-bubble-text" class="size-5 text-gray-500" />{{ __('Comments') }}
                </h1>
            </header>
            <EmptyStateMenu :heading="__('No comments yet. They appear here as soon as members answer a post.')">
                <EmptyStateItem
                    icon="mail-chat-bubble-text"
                    :heading="__('Comments')"
                    :description="__('Replies to posts, one level deep')"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('Comments')" icon="mail-chat-bubble-text" />

            <CommandPaletteItem
                :text="[__('Community'), __('Comments')]"
                :url="listingUrl"
                icon="mail-chat-bubble-text"
                prioritize
            />

            <Listing
                :url="listingUrl"
                :filters="filters"
                :sort-column="sortColumn"
                :sort-direction="sortDirection"
                :action-url="actionUrl"
                :action-context="{ type: 'comment' }"
                preferences-prefix="community.comments"
                push-query
            >
                <template #cell-excerpt="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium">
                            {{ row.excerpt }}
                            <Badge v-if="row.is_reply" pill :text="__('Reply')" class="ms-1" />
                        </span>
                        <span v-if="row.hidden && row.hidden_reason" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Hidden') }}: {{ row.hidden_reason }}
                        </span>
                    </div>
                </template>

                <template #cell-post="{ row }">
                    <span class="text-gray-600 dark:text-gray-400">{{ row.post || '—' }}</span>
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

                <template #cell-hidden="{ row }">
                    <Badge v-if="row.hidden" pill color="red" :text="__('Hidden')" />
                    <Badge v-else pill color="green" :text="__('Visible')" />
                </template>

                <template #cell-created_at="{ row }">
                    <date-time :of="row.created_at" />
                </template>
            </Listing>
        </template>
    </div>
</template>
