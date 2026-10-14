<script setup>
import { Badge, Widget } from '@statamic/cms/ui';

defineProps({
    title: { type: String, required: true },
    url: { type: String, required: true },
    stats: { type: Object, required: true },
});
</script>

<template>
    <Widget :title="title" icon="media-news-paper" :href="url">
        <ul class="divide-y divide-gray-200 dark:divide-gray-800">
            <li class="flex items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm">{{ __('Posts, last 7 days') }}</span>
                <span class="text-sm font-medium tabular-nums">{{ stats.posts }}</span>
            </li>
            <li class="flex items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm">{{ __('Comments, last 7 days') }}</span>
                <span class="text-sm font-medium tabular-nums">{{ stats.comments }}</span>
            </li>
            <li class="flex items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm">{{ __('Waiting for approval') }}</span>
                <Badge pill :color="stats.pending > 0 ? 'amber' : 'default'" :text="String(stats.pending)" />
            </li>
            <li class="flex items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm">{{ __('Hidden') }}</span>
                <Badge pill :color="stats.hidden > 0 ? 'red' : 'default'" :text="String(stats.hidden)" />
            </li>
        </ul>
    </Widget>
</template>
