<template>
    <div>
        <p class="text-center">{{ currentPhotoPage }} / {{ remainingPhotos }} untagged photos</p>

        <div class="flex justify-center gap-6 mt-2">
            <span
                class="flex items-center group"
                :class="{
                    'cursor-not-allowed text-gray-300': !hasPreviousPage,
                    'hover:text-green-600 cursor-pointer': hasPreviousPage,
                }"
                @click="previous"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-6 mr-4"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18" />
                </svg>
                Previous
            </span>

            <span
                class="flex items-center group"
                :class="{
                    'cursor-not-allowed text-gray-300': !hasNextPage,
                    'hover:text-green-600 cursor-pointer': hasNextPage,
                }"
                @click="next"
            >
                Next
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-6 ml-4"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                </svg>
            </span>
        </div>
    </div>
</template>

<script setup>
import { defineEmits, defineProps, computed } from 'vue';

const props = defineProps({
    previous: Function,
    next: Function,
    paginatedPhotos: {
        type: Object,
        default: () => ({}),
    },
    remainingPhotos: {
        type: Number,
        required: true,
    },
    currentPhotoPage: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['previous', 'next']);

const hasPreviousPage = computed(() => !!props.paginatedPhotos?.prev_page_url);
const hasNextPage = computed(() => !!props.paginatedPhotos?.next_page_url);

const previous = () => {
    if (hasPreviousPage.value) {
        emit('previous');
    }
};

const next = () => {
    if (hasNextPage.value) {
        emit('next');
    }
};
</script>
