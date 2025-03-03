<template>
    <div class="absolute h-2/3 w-[5em] mx-4">
        <div class="h-full flex flex-col items-center space-y-1">
            <!-- Level label -->
            <div class="text-center text-green-700 font-bold text-sm">Level {{ userLevel }}</div>

            <!-- Vertical progress bar container -->
            <div class="relative h-full w-3 bg-gray-200 rounded-full dark:bg-gray-700 flex flex-col-reverse">
                <!-- Existing XP (blue) -->
                <div class="bg-blue-600 w-full rounded-full" :style="{ height: existingXPProgress + '%' }"></div>

                <!-- Newly gained XP (green) -->
                <div
                    class="bg-green-500 w-full rounded-full absolute bottom-0"
                    :style="{ height: newXPProgress + '%' }"
                ></div>

                <!-- Horizontal marker line at current percentage -->
                <div
                    class="absolute left-0 w-full border-t-2 border-red-500"
                    :style="{ bottom: xpProgress + '%' }"
                ></div>

                <!-- Centered percentage label -->
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

const props = defineProps({
    newTags: {
        type: Array,
        required: true,
    },
});

// Simulated current XP and XP required for the next level
const currentXP = ref(120); // Example: Current XP
const xpToNextLevel = ref(200); // Example: XP needed for next level
const userLevel = ref(1); // Example: Current user level

// Function to calculate new XP gained
const calculateXP = (tags) => {
    let totalXP = 0;

    tags.forEach((tag) => {
        totalXP += tag.quantity; // Each category.object tag is worth its quantity
        if (tag.extraTags) {
            totalXP += tag.extraTags.length; // Extra tags (brand, material, custom) add 1 XP each
        }
    });

    return totalXP;
};

// Compute XP
const newXP = computed(() => calculateXP(props.newTags));
const totalXP = computed(() => currentXP.value + newXP.value);
const xpProgress = computed(() => Math.min((totalXP.value / xpToNextLevel.value) * 100, 100));

// Calculate progress bars separately
const existingXPProgress = computed(() => Math.min((currentXP.value / xpToNextLevel.value) * 100, 100));
const newXPProgress = computed(() => Math.min((newXP.value / xpToNextLevel.value) * 100, 100));
</script>

<style scoped></style>
