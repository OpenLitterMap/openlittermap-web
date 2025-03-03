<template>
    <div class="absolute h-2/3 w-[5em] mx-4">
        <div class="h-full flex flex-col items-center space-y-1">
            <!-- Level label -->
            <div class="text-center text-green-700 font-bold text-sm">Level {{ userLevel }}</div>

            <!-- Vertical progress bar container -->
            <div class="relative h-full w-3 bg-gray-200 rounded-full dark:bg-gray-700">
                <!-- Total XP (blue): from 0% to xpProgress -->
                <div
                    class="bg-blue-600 w-full rounded-full absolute bottom-0"
                    :style="{ height: xpProgress + '%' }"
                ></div>

                <!-- Newly gained XP (green): from existingXPProgress% to xpProgress% -->
                <div
                    class="bg-green-500 w-full rounded-full absolute"
                    :style="{
                        bottom: existingXPProgress + '%',
                        height: xpProgress - existingXPProgress + '%',
                    }"
                ></div>

                <!-- Horizontal marker line at the total XP percentage -->
                <div
                    class="absolute left-0 w-full border-t-2 border-red-500"
                    :style="{ bottom: xpProgress + '%' }"
                ></div>

                <!-- Centered percentage label at the total XP percentage -->
                <div
                    class="absolute left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-xs font-bold text-red-500"
                    :style="{
                        bottom: `calc(${xpProgress}% - 1.25em)`,
                        left: '-1.5em',
                    }"
                >
                    {{ xpProgress }}%
                </div>
            </div>

            <!-- XP gained -->
            <div class="text-xs font-bold text-green-500">+{{ newXP }} XP</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, defineProps } from 'vue';
import { useUserStore } from '../../../../stores/user/index.js';

const props = defineProps({
    newTags: {
        type: Array,
        required: true,
    },
});

const userStore = useUserStore();

// Reactive references for XP data
const currentXP = ref(userStore.user.xp_redis);
const xpRequired = ref(userStore.user.next_level.xp);
const userLevel = ref(userStore.user.level);

// Function to calculate new XP gained
const calculateXP = (tags) => {
    let totalXP = 0;
    tags.forEach((tag) => {
        totalXP += tag.quantity;
        if (tag.extraTags) {
            tag.extraTags.forEach((extra) => {
                if (extra.selected) {
                    totalXP++;
                }
            });
        }
    });
    return totalXP;
};

// newXP: XP gained from the new tags
const newXP = computed(() => calculateXP(props.newTags));

// totalXP: user's final XP after adding newXP
const totalXP = computed(() => currentXP.value + newXP.value);

// xpProgress: total XP as a percentage of xpRequired
const xpProgress = computed(() => Math.round(Math.min((totalXP.value / xpRequired.value) * 100, 100)));

// existingXPProgress: user's old XP (before newXP) as a percentage of xpRequired
const existingXPProgress = computed(() => Math.round(Math.min((currentXP.value / xpRequired.value) * 100, 100)));
</script>

<style scoped></style>
