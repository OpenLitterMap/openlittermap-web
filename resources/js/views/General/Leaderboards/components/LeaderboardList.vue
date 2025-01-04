<template>
    <div class="w-[800px] mx-auto px-4 py-8">

        <LeaderboardFilters
            :location-id="locationId"
            :location-type="locationType"
        />

        <!-- Empty Leaderboard Message. Needs translation -->
        <p
            v-if="leaders.length === 0"
            class="text-white font-semibold text-2xl text-center mt-4"
        >Nobody has uploaded yet!</p>

        <!-- Leaderboard List -->
        <div
            v-else
            v-for="(leader, index) in leaders"
            :key="index"
            class="relative bg-white rounded-lg shadow-md px-2 py-4 mb-4 flex items-center
            text-[#011638]  text-sm md:text-base transition-transform duration-150 hover:scale-105"
        >
            <!-- Medal -->
            <div class="medal absolute top-[-12px] left-[-12px] w-8">
                <img v-if="leader.rank === 1" :src="goldMedal" alt="Gold medal" />
                <img v-else-if="leader.rank === 2" :src="silverMedal" alt="Silver medal" />
                <img v-else-if="leader.rank === 3" :src="bronzeMedal" alt="Bronze medal" />
            </div>

            <!-- Rank -->
            <div class="flex items-center text-center w-[96[x]">
                <span>{{ getPosition(leader.rank || index + 1) }}</span>
                <div class="flag mt-2">
                    <img
                        v-if="leader.global_flag"
                        :src="getCountryFlag(leader.global_flag)"
                        :alt="leader.global_flag"
                        class="w-8 h-8 rounded-full object-cover"
                    />
                </div>
            </div>

            <!-- Details -->
            <div class="details flex-1 ml-4">
                <div class="name font-medium">
                    {{ leader.name || leader.username || t('common.anonymous') }}
                </div>
                <div v-if="leader.team" class="team text-sm text-gray-500">
                    {{ t('common.team') }} {{ leader.team }}
                </div>
                <div v-if="leader.social" class="social-container flex flex-wrap gap-2 mt-2">
                    <a
                        v-for="(link, type) in leader.social"
                        :key="type"
                        :href="link"
                        target="_blank"
                        class="text-blue-500 hover:scale-110 transition-transform"
                    >
                        <i :class="type === 'personal' ? 'fa fa-link' : `fa fa-${type}`"></i>
                    </a>
                </div>
            </div>

            <!-- XP -->
            <div class="flex justify-evenly">
                <div class="value font-medium">{{ leader.xp }}</div>
                <div class="text text-sm text-gray-500">XP</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps } from 'vue';
import { useI18n } from "vue-i18n";
import LeaderboardFilters from "./LeaderboardFilters.vue";

const { t } = useI18n();
import goldMedal from "@/assets/icons/medals/gold-medal-2.png";
import silverMedal from "@/assets/icons/medals/silver-medal-2.png";
import bronzeMedal from "@/assets/icons/medals/bronze-medal-2.png";

defineProps({
    leaders: {
        type: Array,
        required: true,
    },
    locationId: {
        type: [String, Number],
        required: false,
        default: 0
    },
    locationType: {
        type: String,
        required: false,
        default: ''
    },
});

/**
 * Get the country flag URL
 */
const getCountryFlag = (country) => {
    return country ? `/assets/icons/flags/${country.toLowerCase()}.png` : '';
};

/**
 * Get the ordinal position
 */
const getPosition = (rank) => {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const value = rank % 100;
    return rank + (suffixes[(value - 20) % 10] || suffixes[value] || suffixes[0]);
};
</script>
