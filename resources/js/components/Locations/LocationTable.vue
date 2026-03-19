<template>
    <div class="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
        <table class="w-full">
            <thead class="sticky top-0 z-10 bg-slate-900/95 backdrop-blur-sm">
                <tr class="border-b border-white/10">
                    <th class="px-4 py-3 text-left text-xs font-medium text-white/40 uppercase tracking-wider w-12">
                        #
                    </th>
                    <th
                        v-for="col in activeColumns"
                        :key="col.field"
                        class="px-4 py-3 text-xs font-medium uppercase tracking-wider cursor-pointer select-none hover:text-emerald-300 transition-colors"
                        :class="[
                            col.align === 'right' ? 'text-right' : 'text-left',
                            sortField === col.field ? 'text-emerald-400' : 'text-white/40',
                        ]"
                        @click="$emit('sort', col.field)"
                    >
                        <span class="inline-flex items-center gap-1">
                            {{ col.label }}
                            <svg
                                v-if="sortField === col.field"
                                class="w-3 h-3 transition-transform"
                                :class="{ 'rotate-180': sortDir === 'asc' }"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                />
                            </svg>
                        </span>
                    </th>
                    <!-- Chevron spacer -->
                    <th class="w-8"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(loc, i) in locations"
                    :key="loc.id"
                    class="border-b border-white/5 hover:bg-white/5 cursor-pointer transition-colors group"
                    @click="$emit('navigate', loc)"
                >
                    <!-- Rank -->
                    <td class="px-4 py-3 w-12">
                        <span v-if="i < 3" class="text-base">{{ medals[i] }}</span>
                        <span v-else class="text-sm text-white/30 tabular-nums">{{ i + 1 }}</span>
                    </td>

                    <!-- Name + created/updated subtext -->
                    <td class="px-4 py-3">
                        <div class="text-white font-medium">
                            <span v-if="type === 'country' && loc.shortcode" class="mr-2">{{
                                flag(loc.shortcode)
                            }}</span>
                            {{ loc.name }}
                        </div>
                        <div v-if="loc.created_by" class="text-xs text-white/25 mt-0.5">
                            est. {{ timeAgo(loc.created_at) }} by {{ loc.created_by }}
                            <span v-if="loc.last_updated_by">
                                · last update {{ timeAgo(loc.last_updated_at) }} by {{ loc.last_updated_by }}</span
                            >
                        </div>
                    </td>

                    <!-- Tags + % -->
                    <td class="px-4 py-3 text-right">
                        <div class="text-white/80 tabular-nums">{{ fmt(loc.total_tags) }}</div>
                        <div v-if="loc.pct_tags != null" class="text-[11px] text-white/25 tabular-nums">
                            {{ loc.pct_tags }}%
                        </div>
                    </td>

                    <!-- Photos + % -->
                    <td class="px-4 py-3 text-right">
                        <div class="text-white/80 tabular-nums">{{ fmt(loc.total_images) }}</div>
                        <div v-if="loc.pct_photos != null" class="text-[11px] text-white/25 tabular-nums">
                            {{ loc.pct_photos }}%
                        </div>
                    </td>

                    <!-- Contributors -->
                    <td v-if="hasMeta" class="px-4 py-3 text-right text-white/80 tabular-nums">
                        {{ fmt(loc.total_members) }}
                    </td>

                    <!-- Avg tags/person -->
                    <td v-if="hasMeta" class="px-4 py-3 text-right text-white/80 tabular-nums">
                        {{ loc.avg_tags_per_person ?? 0 }}
                    </td>

                    <!-- Chevron -->
                    <td class="px-2 py-3 text-right">
                        <svg
                            class="w-4 h-4 text-white/20 group-hover:text-white/60 transition-colors inline-block"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </td>
                </tr>

                <!-- Empty state -->
                <tr v-if="locations.length === 0">
                    <td :colspan="hasMeta ? 7 : 5" class="px-4 py-12 text-center text-white/40">
                        No locations match your search.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    locations: { type: Array, required: true },
    type: { type: String, default: 'country' },
    sortField: { type: String, default: 'total_tags' },
    sortDir: { type: String, default: 'desc' },
});

defineEmits(['sort', 'navigate']);

const medals = ['🥇', '🥈', '🥉'];

const hasMeta = computed(() => props.locations.some((l) => l.total_members != null));

const baseColumns = [
    { field: 'name', label: 'Name', align: 'left' },
    { field: 'total_tags', label: 'Tags', align: 'right' },
    { field: 'total_images', label: 'Photos', align: 'right' },
];

const metaColumns = [
    { field: 'total_members', label: 'Contributors', align: 'right' },
    { field: 'avg_tags_per_person', label: 'Avg / Person', align: 'right' },
];

const activeColumns = computed(() => (hasMeta.value ? [...baseColumns, ...metaColumns] : baseColumns));

function fmt(n) {
    return (n ?? 0).toLocaleString();
}

function flag(shortcode) {
    if (!shortcode || shortcode.length !== 2) return '';
    return shortcode.toUpperCase().replace(/./g, (c) => String.fromCodePoint(127397 + c.charCodeAt(0)));
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const seconds = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    const intervals = [
        [31536000, 'year'],
        [2592000, 'month'],
        [604800, 'week'],
        [86400, 'day'],
        [3600, 'hour'],
        [60, 'minute'],
    ];
    for (const [secs, label] of intervals) {
        const count = Math.floor(seconds / secs);
        if (count >= 1) return `${count} ${label}${count > 1 ? 's' : ''} ago`;
    }
    return 'just now';
}
</script>
