<template>
    <div class="flex justify-center mt-8">
        <nav class="bg-white/10 backdrop-blur-sm rounded-lg p-2 flex items-center gap-2">
            <!-- Previous Button -->
            <button
                @click="changePage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="px-4 py-2 rounded-lg text-white transition-colors"
                :class="
                    currentPage === 1 ? 'bg-white/10 opacity-50 cursor-not-allowed' : 'bg-white/20 hover:bg-white/30'
                "
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Page Numbers -->
            <div class="flex gap-1">
                <!-- First Page -->
                <button
                    v-if="showFirstPage"
                    @click="changePage(1)"
                    class="px-3 py-2 rounded-lg text-white bg-white/20 hover:bg-white/30 transition-colors"
                >
                    1
                </button>

                <!-- Ellipsis -->
                <span v-if="showFirstEllipsis" class="px-2 py-2 text-white">...</span>

                <!-- Visible Pages -->
                <button
                    v-for="page in visiblePages"
                    :key="page"
                    @click="changePage(page)"
                    class="px-3 py-2 rounded-lg text-white transition-colors"
                    :class="
                        page === currentPage
                            ? 'bg-gradient-to-r from-green-500 to-blue-500 font-bold'
                            : 'bg-white/20 hover:bg-white/30'
                    "
                >
                    {{ page }}
                </button>

                <!-- Ellipsis -->
                <span v-if="showLastEllipsis" class="px-2 py-2 text-white">...</span>

                <!-- Last Page -->
                <button
                    v-if="showLastPage"
                    @click="changePage(totalPages)"
                    class="px-3 py-2 rounded-lg text-white bg-white/20 hover:bg-white/30 transition-colors"
                >
                    {{ totalPages }}
                </button>
            </div>

            <!-- Next Button -->
            <button
                @click="changePage(currentPage + 1)"
                :disabled="currentPage === totalPages"
                class="px-4 py-2 rounded-lg text-white transition-colors"
                :class="
                    currentPage === totalPages
                        ? 'bg-white/10 opacity-50 cursor-not-allowed'
                        : 'bg-white/20 hover:bg-white/30'
                "
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <!-- Page Info -->
            <div class="ml-4 px-4 py-2 bg-white/20 rounded-lg text-white text-sm">
                <span class="font-semibold">{{ pagination.from }}-{{ pagination.to }}</span>
                <span class="text-white/70"> of </span>
                <span class="font-semibold">{{ pagination.total }}</span>
            </div>
        </nav>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    pagination: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['page-change']);

const currentPage = computed(() => props.pagination.current_page || 1);
const totalPages = computed(() => props.pagination.last_page || 1);

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    const halfVisible = Math.floor(maxVisible / 2);

    let start = Math.max(2, currentPage.value - halfVisible);
    let end = Math.min(totalPages.value - 1, currentPage.value + halfVisible);

    // Adjust if we're near the beginning or end
    if (currentPage.value <= halfVisible + 1) {
        end = Math.min(totalPages.value - 1, maxVisible);
    }
    if (currentPage.value >= totalPages.value - halfVisible) {
        start = Math.max(2, totalPages.value - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});

const showFirstPage = computed(() => {
    return !visiblePages.value.includes(1) && totalPages.value > 1;
});

const showLastPage = computed(() => {
    return !visiblePages.value.includes(totalPages.value) && totalPages.value > 1;
});

const showFirstEllipsis = computed(() => {
    return visiblePages.value.length > 0 && visiblePages.value[0] > 2;
});

const showLastEllipsis = computed(() => {
    return visiblePages.value.length > 0 && visiblePages.value[visiblePages.value.length - 1] < totalPages.value - 1;
});

const changePage = (page) => {
    if (page < 1 || page > totalPages.value || page === currentPage.value) return;
    emit('page-change', page);
};
</script>
