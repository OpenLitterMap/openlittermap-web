<template>
    <div class="container mx-auto w-full py-4">
        <div class="my-4 md:text-right text-center">
            <select v-model="sortLocationsBy" class="border border-gray-300 rounded p-2">
                <option v-for="opt in options" :key="opt.value" :value="opt.value">
                    {{ opt.text }}
                </option>
            </select>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useWorldStore } from '../../../stores/world/index.js';
const worldStore = useWorldStore();
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const options = ref([
    { text: 'A-Z', value: 'alphabetical' },
    { text: t('Most Litter Tags'), value: 'most-data' },
    { text: t('Most Litter Tags Per Person'), value: 'most-data-per-person' },
    { text: 'Total Contributors', value: 'total-contributors' },
    { text: 'First Created', value: 'first-created' },
    { text: 'Most Recently Created', value: 'most-recently-created' },
    { text: 'Most Recently Updated', value: 'most-recently-updated' },
]);

const sortLocationsBy = computed({
    get() {
        return worldStore.sortLocationsBy;
    },
    set(value) {
        worldStore.setSortLocationsBy(value); // corrected here
    },
});
</script>
