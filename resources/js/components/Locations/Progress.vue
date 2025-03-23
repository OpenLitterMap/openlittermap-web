<template>
    <div class="flex w-full mt-4">
        <div class="min-w-[500px] mx-auto">
            <!-- XP bar variables -->
            <div class="flex mb-1">
                <h4 class="flex-1 text-white">
                    {{ t('location.previous-target') }}:
                    <br />
                    <strong class="text-white"> {{ commas(previousXp) }} {{ t('location.litter') }} </strong>
                </h4>
                <h4 class="text-white">
                    {{ t('location.next-target') }}:
                    <br />
                    <strong class="text-white"> {{ commas(nextXp) }} {{ t('location.litter') }} </strong>
                </h4>
            </div>

            <progress
                class="w-full h-4 rounded bg-green-500"
                :value="currentValue"
                max="100"
                style="border-radius: 10px"
            ></progress>

            <p v-if="loading" class="text-center text-white mb-2">...%</p>
            <p v-else class="text-center text-white mb-2">{{ progress }}%</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useWorldStore } from '@/stores/world';

const { t } = useI18n();

// Define component props
const props = defineProps({
    loading: Boolean,
});

// Access the Pinia store
const worldStore = useWorldStore();

// Map state from the store (previously from Vuex)
const littercoin = computed(() => worldStore.littercoin);
const previousXp = computed(() => worldStore.level.previousXp);
const nextXp = computed(() => worldStore.level.nextXp);
const total_litter = computed(() => worldStore.total_litter);
const total_photos = computed(() => worldStore.total_photos);

// Calculate progress percentage between levels
const progress = computed(() => {
    const range = nextXp.value - previousXp.value;
    const startVal = total_litter.value - previousXp.value;
    const x = ((startVal * 100) / range).toFixed(2);

    console.log({ x });

    return x;
});

// Utility method for formatting numbers with commas
function commas(n) {
    return parseInt(n).toLocaleString();
}

// Compute the current progress value as a percentage
const currentValue = computed(() => {
    const range = props.xpneeded - props.startingxp;
    const startVal = props.currentxp - props.startingxp;

    return 50;
    return (startVal * 100) / range;
});
</script>

<style scoped>
/* Remove default appearance */
progress {
    -webkit-appearance: none;
    appearance: none;
}

/* Background of the progress bar */
progress::-webkit-progress-bar {
    background-color: #e5e7eb; /* Tailwind's gray-200 */
    border-radius: 9999px; /* Fully rounded */
}

/* Progress value */
progress::-webkit-progress-value {
    background-color: #10b981; /* Tailwind's green-500 */
    border-radius: 9999px; /* Fully rounded */
}

/* Firefox support */
progress::-moz-progress-bar {
    background-color: #10b981;
    border-radius: 9999px;
}
</style>
