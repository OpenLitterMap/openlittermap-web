<template>
    <div class="w-full max-w-3xl mx-auto">
        <!-- Empty State -->
        <p v-if="leaders.length === 0" class="text-white/50 text-center py-12 text-lg">
            {{ t('Nobody has uploaded yet!') }}
        </p>

        <!-- Leaderboard Cards -->
        <component
            :is="leader.public_profile ? 'router-link' : 'div'"
            v-for="(leader, index) in leaders"
            :key="index"
            :to="leader.public_profile ? `/profile/${leader.user_id}` : undefined"
            class="relative bg-white/5 border border-white/10 rounded-xl px-4 py-3 mb-3 flex items-center gap-3 transition-all duration-150 hover:bg-white/[0.08] hover:-translate-y-0.5 hover:shadow-lg hover:shadow-black/20"
            :class="{ 'cursor-pointer': leader.public_profile }"
        >
            <!-- Medal -->
            <div class="absolute -top-2.5 -left-2.5 w-7">
                <img v-if="leader.rank === 1" :src="goldMedal" alt="Gold medal" />
                <img v-else-if="leader.rank === 2" :src="silverMedal" alt="Silver medal" />
                <img v-else-if="leader.rank === 3" :src="bronzeMedal" alt="Bronze medal" />
            </div>

            <!-- Rank -->
            <div class="shrink-0 w-12 text-center text-white/40 text-sm font-medium">
                {{ getPosition(leader.rank || index + 1) }}
            </div>

            <!-- Flag -->
            <div class="shrink-0 w-8">
                <img
                    v-if="leader.global_flag"
                    :src="getCountryFlag(leader.global_flag)"
                    :alt="leader.global_flag"
                    class="w-7 h-7 rounded-full object-cover border border-white/10"
                />
            </div>

            <!-- Name & Team -->
            <div class="min-w-0 flex-1">
                <div class="text-white font-medium truncate">
                    {{ leader.name || leader.username || t('Anonymous') }}
                </div>
                <div v-if="leader.team" class="text-white/40 text-xs truncate">
                    {{ t('Team') }} {{ leader.team }}
                </div>
            </div>

            <!-- Social Icons (hidden on mobile) -->
            <div v-if="leader.social" class="hidden sm:flex gap-3 shrink-0">
                <a
                    v-for="(link, type) in leader.social"
                    :key="type"
                    :href="link"
                    target="_blank"
                    class="text-white/40 hover:text-white transition-colors"
                >
                    <i :class="type === 'personal' ? 'fa fa-link' : `fa fa-${type}`"></i>
                </a>
            </div>

            <!-- XP -->
            <div class="shrink-0 text-right">
                <span class="text-white font-bold tabular-nums">{{ Number(leader.xp).toLocaleString() }}</span>
                <span class="text-white/40 text-xs ml-1">XP</span>
            </div>
        </component>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

import goldMedal from '@/assets/icons/medals/gold-medal-2.png';
import silverMedal from '@/assets/icons/medals/silver-medal-2.png';
import bronzeMedal from '@/assets/icons/medals/bronze-medal-2.png';

defineProps({
    leaders: {
        type: Array,
        required: true,
    },
});

const getCountryFlag = (country) => {
    return country ? `/assets/icons/flags/${country.toLowerCase()}.png` : '';
};

const getPosition = (rank) => {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const value = rank % 100;
    return rank + (suffixes[(value - 20) % 10] || suffixes[value] || suffixes[0]);
};
</script>
