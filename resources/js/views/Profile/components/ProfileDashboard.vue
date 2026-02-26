<template>
    <div class="space-y-6">
        <!-- Level Card -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Level</div>
                    <div class="text-white text-4xl font-bold">{{ profileStore.level.level }}</div>
                    <div class="text-emerald-400 text-sm font-medium">{{ profileStore.level.title }}</div>
                </div>
                <div class="text-right">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Total XP</div>
                    <div class="text-white text-3xl font-bold tabular-nums">
                        {{ profileStore.stats.xp?.toLocaleString() }}
                    </div>
                    <div v-if="profileStore.user.member_since" class="text-white/30 text-xs mt-1">
                        Member since {{ profileStore.user.member_since }}
                    </div>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="mt-4">
                <div class="flex justify-between text-xs text-white/40 mb-1.5">
                    <span>{{ profileStore.level.xp_into_level?.toLocaleString() }} XP into level</span>
                    <span>{{ profileStore.level.xp_remaining?.toLocaleString() }} XP to next</span>
                </div>
                <div class="w-full h-3 bg-white/10 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                        :style="{ width: profileStore.levelProgress + '%' }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="bg-white/5 border border-white/10 rounded-xl px-5 py-4"
            >
                <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">
                    {{ stat.label }}
                </div>
                <div class="text-white text-2xl font-bold tabular-nums tracking-tight">
                    {{ stat.value }}
                </div>
                <div v-if="stat.sub" class="text-white/30 text-xs mt-0.5">{{ stat.sub }}</div>
            </div>
        </div>

        <!-- Rank & Achievements -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Rank -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-3">Global Rank</div>
                <div class="text-white text-3xl font-bold tabular-nums">
                    #{{ profileStore.rank.global_position?.toLocaleString() }}
                </div>
                <div class="text-white/40 text-sm mt-1">
                    of {{ profileStore.rank.global_total?.toLocaleString() }} users
                    <span v-if="profileStore.rank.percentile > 0" class="text-emerald-400">
                        &middot; Top {{ profileStore.rank.percentile }}%
                    </span>
                </div>
            </div>

            <!-- Achievements -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-3">Achievements</div>
                <div class="text-white text-3xl font-bold tabular-nums">
                    {{ profileStore.achievements.unlocked?.toLocaleString() }}
                </div>
                <div class="text-white/40 text-sm mt-1">
                    of {{ profileStore.achievements.total?.toLocaleString() }} unlocked
                </div>
                <router-link
                    to="/achievements"
                    class="inline-block mt-3 text-emerald-400 text-sm hover:text-emerald-300 transition"
                >
                    View all &rarr;
                </router-link>
            </div>
        </div>

        <!-- Locations & Team -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Locations -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">Your Impact</div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-white text-2xl font-bold tabular-nums">
                            {{ profileStore.locations.countries }}
                        </div>
                        <div class="text-white/40 text-sm">Countries</div>
                    </div>
                    <div>
                        <div class="text-white text-2xl font-bold tabular-nums">
                            {{ profileStore.locations.states }}
                        </div>
                        <div class="text-white/40 text-sm">States</div>
                    </div>
                    <div>
                        <div class="text-white text-2xl font-bold tabular-nums">
                            {{ profileStore.locations.cities }}
                        </div>
                        <div class="text-white/40 text-sm">Cities</div>
                    </div>
                </div>
            </div>

            <!-- Team + Littercoin -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <div v-if="profileStore.team" class="mb-4">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Active Team</div>
                    <router-link
                        :to="`/teams/${profileStore.team.id}`"
                        class="text-white font-semibold hover:text-emerald-400 transition"
                    >
                        {{ profileStore.team.name }}
                    </router-link>
                </div>
                <div>
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">Littercoin</div>
                    <div class="text-white text-2xl font-bold tabular-nums">
                        {{ profileStore.stats.littercoin?.toLocaleString() || 0 }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Stats -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">
                OpenLitterMap Global
            </div>
            <div class="grid grid-cols-2 gap-4 text-center">
                <div>
                    <div class="text-white text-2xl font-bold tabular-nums">
                        {{ profileStore.global_stats.total_photos?.toLocaleString() }}
                    </div>
                    <div class="text-white/40 text-sm">Total Photos</div>
                </div>
                <div>
                    <div class="text-white text-2xl font-bold tabular-nums">
                        {{ profileStore.global_stats.total_litter?.toLocaleString() }}
                    </div>
                    <div class="text-white/40 text-sm">Total Litter Tagged</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useProfileStore } from '@stores/profile.js';

const profileStore = useProfileStore();

const photoPercent = computed(() => {
    const p = profileStore.stats.photo_percent;
    return p > 0 ? `${p}% of global` : null;
});

const tagPercent = computed(() => {
    const p = profileStore.stats.tag_percent;
    return p > 0 ? `${p}% of global` : null;
});

const statCards = computed(() => [
    { label: 'Uploads', value: profileStore.stats.uploads?.toLocaleString(), sub: photoPercent.value },
    { label: 'Litter Tagged', value: profileStore.stats.litter?.toLocaleString(), sub: tagPercent.value },
    { label: 'XP', value: profileStore.stats.xp?.toLocaleString() },
    { label: 'Day Streak', value: profileStore.stats.streak?.toLocaleString() },
]);
</script>
