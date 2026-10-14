<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Header, Listing, Button, CommandPaletteItem, EmptyStateMenu, EmptyStateItem, Icon, DropdownItem } from '@statamic/cms/ui';

/**
 * The rooms. A handful of curated rows, so the Listing runs client side
 * (`items`) and handles search and sorting itself; it only needs to know how
 * to reload.
 */
defineProps({
    rooms: { type: Array, required: true },
    columns: { type: Array, required: true },
    createUrl: { type: String, required: true },
    canManage: { type: Boolean, default: false },
});

const reload = () => router.reload({ only: ['rooms'] });
</script>

<template>
    <Head :title="__('Rooms')" />

    <div class="max-w-page mx-auto" data-max-width-wrapper>
        <template v-if="rooms.length === 0">
            <header class="py-8 pt-16 text-center">
                <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                    <Icon name="groups" class="size-5 text-gray-500" />{{ __('Rooms') }}
                </h1>
            </header>
            <EmptyStateMenu :heading="__('A room is a place to post in: a region, a style of singing, a topic. Members join the rooms they care about.')">
                <EmptyStateItem
                    v-if="canManage"
                    :href="createUrl"
                    icon="groups"
                    :heading="__('Create room')"
                    :description="__('No rooms yet')"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('Rooms')" icon="groups">
                <CommandPaletteItem
                    v-if="canManage"
                    category="Actions"
                    :text="__('Create room')"
                    icon="groups"
                    :url="createUrl"
                    v-slot="{ text, url }"
                >
                    <Button :href="url" :text="text" variant="primary" />
                </CommandPaletteItem>
            </Header>

            <Listing
                :items="rooms"
                :columns="columns"
                preferences-prefix="community.rooms"
                @refreshing="reload"
            >
                <template #cell-name="{ row }">
                    <span class="inline-flex items-center gap-2">
                        <span v-if="row.icon" aria-hidden="true">{{ row.icon }}</span>
                        <Link v-if="canManage" :href="row.edit_url" class="font-medium">{{ row.name }}</Link>
                        <span v-else class="font-medium">{{ row.name }}</span>
                    </span>
                </template>

                <template #cell-members="{ row }">
                    <span class="tabular-nums">{{ row.members }}</span>
                </template>

                <template #cell-posts="{ row }">
                    <span class="tabular-nums">{{ row.posts }}</span>
                </template>

                <template #cell-pinned="{ row }">
                    <span v-if="row.pinned">{{ row.pinned }}</span>
                    <span v-else class="text-gray-500 dark:text-gray-400">&mdash;</span>
                </template>

                <template v-if="canManage" #prepended-row-actions="{ row }">
                    <DropdownItem :text="__('Edit')" icon="edit" :href="row.edit_url" />
                </template>
            </Listing>
        </template>
    </div>
</template>
