<template>
    <div class="grid grid-cols-3 gap-3">
        <div
            v-for="stat in visibleStats"
            :key="stat.label"
            class="bg-white/5 border border-white/10 rounded-xl px-4 py-5 text-center transition-all duration-200 hover:bg-white/[0.08] hover:border-white/15 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-black/20"
        >
            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1.5">
                {{ stat.label }}
            </div>
            <div class="text-white text-2xl sm:text-3xl font-bold tabular-nums tracking-tight">
                <span v-if="loading" class="inline-block w-16 h-7 bg-white/10 rounded animate-pulse"></span>
                <template v-else>{{ format(stat.value) }}</template>
            </div>
            <!-- Subtext (e.g. "of 12,345 users (11.1%)") -->
            <div v-if="!loading && stat.subtext" class="text-[11px] mt-1.5 text-white/30 tabular-nums">
                {{ stat.subtext }}
            </div>
            <!-- Activity delta -->
            <div
                v-if="!loading && stat.delta"
                class="text-[11px] mt-1 tabular-nums"
                :class="stat.delta.value > 0 ? 'text-emerald-400/70' : 'text-white/30'"
            >
                <template v-if="stat.delta.value > 0">
                    +{{ format(stat.delta.value) }} {{ stat.delta.label }}
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, default: null },
    activity: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const visibleStats = computed(() => {
    const s = props.stats;
    if (!s) return [];

    const list = [
        { label: 'Tags', value: s.tags, activityKey: 'tags' },
        { label: 'Photos', value: s.photos, activityKey: 'photos' },
    ];

    if (s.contributors != null) {
        const contrib = { label: 'Contributors', value: s.contributors };
        const subtextParts = [];

        if (s.total_users > 0) {
            const pct = ((s.contributors / s.total_users) * 100).toFixed(1);
            subtextParts.push(`of ${format(s.total_users)} users (${pct}%)`);
        }

        if (s.countries != null) subtextParts.push(`from ${format(s.countries)} countries`);
        else if (s.state_count != null) subtextParts.push(`from ${format(s.state_count)} states`);
        else if (s.city_count != null) subtextParts.push(`from ${format(s.city_count)} cities`);

        if (subtextParts.length) contrib.subtext = subtextParts.join(' · ');

        list.push(contrib);
    }

    // Attach activity deltas
    const a = props.activity;
    if (a) {
        for (const stat of list) {
            if (!stat.activityKey) continue;

            const todayVal = a.today?.[stat.activityKey] ?? 0;
            const monthVal = a.this_month?.[stat.activityKey] ?? 0;

            if (todayVal > 0) {
                stat.delta = { value: todayVal, label: 'today' };
            } else if (monthVal > 0) {
                stat.delta = { value: monthVal, label: 'this month' };
            }
        }
    }

    return list;
});

function format(n) {
    return (n ?? 0).toLocaleString();
}
</script>
