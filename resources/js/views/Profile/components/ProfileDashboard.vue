<template>
    <div class="space-y-6">
        <!-- Level Card -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Level') }}</div>
                    <div class="text-white text-4xl font-bold">{{ profileStore.level.level }}</div>
                    <div class="text-emerald-400 text-sm font-medium">{{ profileStore.level.title }}</div>
                </div>
                <div class="text-right">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Total XP') }}</div>
                    <div class="text-white text-3xl font-bold tabular-nums">
                        {{ profileStore.stats.xp?.toLocaleString() }}
                    </div>
                    <div v-if="profileStore.user.member_since" class="text-white/30 text-xs mt-1">
                        {{ $t('Member since') }} {{ profileStore.user.member_since }}
                    </div>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="mt-4">
                <div class="flex justify-between text-xs text-white/40 mb-1.5">
                    <span>{{ profileStore.level.xp_into_level?.toLocaleString() }} {{ $t('XP into level') }}</span>
                    <span>{{ profileStore.level.xp_remaining?.toLocaleString() }} {{ $t('XP to next') }}</span>
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

        <!-- Achievements — TODO: Coming soon -->
        <div class="bg-white/5 border border-white/10 rounded-xl px-6 py-4 flex items-center gap-3 opacity-60">
            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest">{{ $t('Achievements') }}</div>
            <div class="text-white/30 text-sm">{{ $t('Coming Soon') }}</div>
        </div>

        <!-- Rank -->
        <div class="bg-white/5 border border-white/10 rounded-xl p-6">
            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-3">{{ $t('Global Rank') }}</div>
            <div class="text-white text-3xl font-bold tabular-nums">
                #{{ profileStore.rank.global_position?.toLocaleString() }}
            </div>
            <div class="text-white/40 text-sm mt-1">
                {{ $t('of') }} {{ profileStore.rank.global_total?.toLocaleString() }} {{ $t('users') }}
                <span v-if="profileStore.rank.percentile >= 50" class="text-emerald-400">
                    &middot; Top {{ profileStore.rank.percentile }}%
                </span>
            </div>
        </div>

        <!-- Locations & Team -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Locations -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-4">{{ $t('Your Impact') }}</div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-white text-2xl font-bold tabular-nums">
                            {{ profileStore.locations.countries }}
                        </div>
                        <div class="text-white/40 text-sm">{{ $t('Countries') }}</div>
                    </div>
                    <div>
                        <div class="text-white text-2xl font-bold tabular-nums">
                            {{ profileStore.locations.states }}
                        </div>
                        <div class="text-white/40 text-sm">{{ $t('States') }}</div>
                    </div>
                    <div>
                        <div class="text-white text-2xl font-bold tabular-nums">
                            {{ profileStore.locations.cities }}
                        </div>
                        <div class="text-white/40 text-sm">{{ $t('Cities') }}</div>
                    </div>
                </div>
            </div>

            <!-- Team + Littercoin -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <div v-if="profileStore.team" class="mb-4">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Active Team') }}</div>
                    <router-link
                        :to="`/teams/${profileStore.team.id}`"
                        class="text-white font-semibold hover:text-emerald-400 transition"
                    >
                        {{ profileStore.team.name }}
                    </router-link>
                </div>
                <div>
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Littercoin') }}</div>
                    <div class="text-white text-2xl font-bold tabular-nums">
                        {{ profileStore.stats.littercoin?.toLocaleString() || 0 }}
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProfileStore } from '@stores/profile.js';

const { t: $t } = useI18n();
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
    { label: $t('Uploads'), value: profileStore.stats.uploads?.toLocaleString(), sub: photoPercent.value },
    { label: $t('Litter Tagged'), value: profileStore.stats.litter?.toLocaleString(), sub: tagPercent.value },
    { label: $t('XP'), value: profileStore.stats.xp?.toLocaleString() },
    { label: $t('Day Streak'), value: profileStore.stats.streak?.toLocaleString() },
]);
</script>
