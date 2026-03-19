<template>
    <div class="flex flex-col md:flex-row justify-between max-w-3xl mx-auto mb-4">
        <div class="p-3 flex-1">
            <h1 class="text-lg text-center">
                <strong class="text-black font-extrabold">
                    {{ t('Tags') }}
                </strong>
            </h1>
            <h1 class="text-2xl md:text-4xl text-center text-white">
                <strong>
                    <span v-if="loading">...</span>
                    <NumberAnimation
                        v-else
                        :from="previousTotalLitter"
                        :to="totalLitter"
                        :duration="3"
                        :delay="1"
                        easing="easeOutExpo"
                        :format="commas"
                    />
                </strong>
            </h1>
        </div>

        <div class="p-3 flex-1">
            <h1 class="text-lg text-center">
                <strong class="text-black font-extrabold">
                    {{ t('Photos') }}
                </strong>
            </h1>
            <h1 class="text-2xl md:text-4xl text-center text-white">
                <strong>
                    <span v-if="loading">...</span>
                    <NumberAnimation
                        v-else
                        :from="previousTotalPhotos"
                        :to="totalPhotos"
                        :duration="3"
                        :delay="1"
                        easing="easeOutExpo"
                        :format="commas"
                    />
                </strong>
            </h1>
        </div>

        <div class="p-3 flex-1">
            <h1 class="text-lg text-center">
                <strong class="text-black font-extrabold">
                    {{ t('Littercoin') }}
                </strong>
            </h1>
            <h1 class="text-2xl md:text-4xl text-center text-white">
                <strong>
                    <span v-if="loading">...</span>
                    <NumberAnimation
                        v-else
                        :from="previousLittercoin"
                        :to="totalLittercoin"
                        :duration="3"
                        :delay="1"
                        easing="easeOutExpo"
                        :format="commas"
                    />
                </strong>
            </h1>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useWorldStore } from '@/stores/world'; // Adjust the path as needed
import NumberAnimation from 'vue-number-animation';

// Define props
const props = defineProps({
    loading: Boolean,
});

// Use i18n for translations
const { t } = useI18n();

// Access your Pinia store
const worldStore = useWorldStore();

// Computed values from the store
const totalLitter = computed(() => worldStore.total_litter);
const totalPhotos = computed(() => worldStore.total_photos);
const totalLittercoin = computed(() => worldStore.littercoin);

// Helper: get previous values from localStorage, update with current value,
// and return the previous value (defaulting to zero)
const previousTotalLitter = computed(() => {
    let prev = 0;
    const stored = localStorage.getItem('total_litter');
    if (stored !== null) {
        prev = parseInt(stored, 10);
    }
    localStorage.setItem('total_litter', totalLitter.value.toString());
    return prev;
});

const previousTotalPhotos = computed(() => {
    let prev = 0;
    const stored = localStorage.getItem('total_photos');
    if (stored !== null) {
        prev = parseInt(stored, 10);
    }
    localStorage.setItem('total_photos', totalPhotos.value.toString());
    return prev;
});

const previousLittercoin = computed(() => {
    let prev = 0;
    const stored = localStorage.getItem('littercoin_owed');
    if (stored !== null) {
        prev = parseInt(stored, 10);
    }
    localStorage.setItem('littercoin_owed', totalLittercoin.value.toString());
    return prev;
});

// Method to format numbers with commas
function commas(n) {
    return parseInt(n, 10).toLocaleString();
}
</script>

<style scoped></style>
