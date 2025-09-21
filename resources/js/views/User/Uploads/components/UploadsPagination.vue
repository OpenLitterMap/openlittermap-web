<script setup>
import { computed } from 'vue';
import { usePhotosStore } from '@/stores/photos';
const store = usePhotosStore();
const currentPage = computed(() => store.currentPage);
const lastPage = computed(() => store.lastPage);
const perPage = computed(() => store.perPage);

const changePage = (page) => {
    if (page >= 1 && page <= lastPage.value) {
        loadPhotos(page);
    }
};

const loadPhotos = async (page = 1) => {
    await store.fetchPhotosOnly(page, perPage.value);
};

const paginationRange = computed(() => {
    const current = currentPage.value;
    const last = lastPage.value;
    const range = [];

    if (last <= 7) {
        for (let i = 1; i <= last; i++) {
            range.push(i);
        }
    } else {
        // Always show first 3 pages
        range.push(1, 2, 3);

        // Add ellipsis if needed
        if (current > 5) {
            range.push('...');
        }

        // Add current page and neighbors if in middle
        if (current > 4 && current < last - 3) {
            range.push(current - 1, current, current + 1);
        }

        // Add ellipsis if needed
        if (current < last - 4) {
            range.push('...');
        }

        // Always show last 3 pages
        range.push(last - 2, last - 1, last);
    }

    // Remove duplicates and sort
    return [...new Set(range.filter((p) => p === '...' || (p >= 1 && p <= last)))];
});
</script>

<template>
    <div v-if="lastPage > 1" class="flex justify-center items-center gap-2 my-6">
        <button
            @click="changePage(1)"
            :disabled="currentPage === 1"
            class="px-3 py-2 bg-gray-900 text-gray-200 border border-gray-700 rounded text-sm min-w-[40px] hover:bg-gray-800 hover:border-gray-600 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        >
            First
        </button>

        <button
            @click="changePage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-2 bg-gray-900 text-gray-200 border border-gray-700 rounded text-sm min-w-[40px] hover:bg-gray-800 hover:border-gray-600 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        >
            Prev
        </button>

        <template v-for="page in paginationRange" :key="page">
            <span v-if="page === '...'" class="px-2 text-gray-500">...</span>
            <button
                v-else
                @click="changePage(page)"
                class="px-3 py-2 border rounded text-sm min-w-[40px] transition-colors"
                :class="
                    page === currentPage
                        ? 'bg-blue-500 text-white border-blue-500'
                        : 'bg-gray-900 text-gray-200 border-gray-700 hover:bg-gray-800 hover:border-gray-600'
                "
            >
                {{ page }}
            </button>
        </template>

        <button
            @click="changePage(currentPage + 1)"
            :disabled="currentPage === lastPage"
            class="px-3 py-2 bg-gray-900 text-gray-200 border border-gray-700 rounded text-sm min-w-[40px] hover:bg-gray-800 hover:border-gray-600 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        >
            Next
        </button>

        <button
            @click="changePage(lastPage)"
            :disabled="currentPage === lastPage"
            class="px-3 py-2 bg-gray-900 text-gray-200 border border-gray-700 rounded text-sm min-w-[40px] hover:bg-gray-800 hover:border-gray-600 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        >
            Last
        </button>
    </div>
</template>
