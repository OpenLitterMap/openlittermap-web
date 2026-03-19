<template>
    <div class="p-8 bg-gradient-to-br from-gray-100 via-gray-200 to-gray-300 min-h-screen">
        <p class="text-4xl font-extrabold mb-8 text-center text-gray-900 tracking-wide">
            OpenLitterMap has been referenced {{ references.length }} times and counting
        </p>

        <div class="flex justify-center mb-8">
            <button
                @click="toggleOrder"
                class="px-6 py-3 bg-blue-600 text-white font-medium rounded-full shadow-lg hover:bg-blue-700 hover:shadow-xl transition duration-300"
            >
                Reverse Order
            </button>
        </div>

        <ul class="space-y-8 max-w-3xl mx-auto">
            <li
                v-for="item in displayedReferences"
                :key="item.title"
                class="bg-white shadow-lg rounded-xl p-6 hover:shadow-2xl transition duration-300"
            >
                <div>
                    <p class="text-sm text-gray-500 mb-2 font-medium">
                        #{{ getOriginalIndex(item) + 1 }} - {{ getDate(item.date) }}
                    </p>

                    <a
                        @click="open(item.link)"
                        class="text-lg font-semibold text-blue-600 hover:text-blue-700 hover:underline cursor-pointer transition"
                    >
                        {{ item.title }}
                    </a>

                    <p class="text-sm text-gray-600 mt-3 font-light">
                        {{ item.author }}
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>

<script setup>
import moment from 'moment'
import { referencesList } from './referencesList.js';
import { onMounted, ref, computed } from "vue";

onMounted(() => {
    window.scrollTo(0, 0);
});

const isReversed = ref(true);

// These could be updated with Tags, eg "AI", "crypto", "GIS", etc
const references = ref(referencesList);

/**
 * Precompute sorted references in ascending order by date
 */
const sortedReferences = ref([...references.value].sort((a, b) => new Date(a.date) - new Date(b.date)));

/**
 * Display references in ascending or descending order
 */
const displayedReferences = computed(() =>
    isReversed.value ? [...sortedReferences.value].reverse() : sortedReferences.value
);

/**
 * Toggle order of references
 */
const toggleOrder = () => {
    isReversed.value = !isReversed.value;
};

/**
 * Return formatted date
 */
const getDate = (date) => {
    return moment(date).format('LL');
}

/**
 * Open link in a new tab
 */
const open = (link) => {
    window.open(link, "_blank");
}

const getOriginalIndex = (item) => {
    const sorted = [...references.value].sort((a, b) => new Date(a.date) - new Date(b.date));

    return sorted.findIndex((reference) => reference.title === item.title);
};

</script>

<style scoped>

</style>
