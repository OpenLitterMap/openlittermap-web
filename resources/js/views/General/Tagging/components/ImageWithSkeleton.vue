<template>
    <div class="relative w-full h-full">
        <!-- Skeleton Loader -->
        <div v-if="isLoading" class="absolute inset-0 animate-pulse">
            <div class="w-full h-full bg-gray-700 rounded-lg flex items-center justify-center">
                <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </div>
            <div class="absolute bottom-4 left-4 right-4 space-y-2">
                <div class="h-3 bg-gray-600 rounded w-1/3"></div>
                <div class="h-3 bg-gray-600 rounded w-1/2"></div>
            </div>
        </div>

        <!-- Actual Image -->
        <img v-show="!isLoading" :src="src" :alt="alt" :class="imageClass" @load="onImageLoad" @error="onImageError" />

        <!-- Error State -->
        <div v-if="hasError" class="absolute inset-0 bg-gray-800 rounded-lg flex flex-col items-center justify-center">
            <svg class="w-16 h-16 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <p class="text-gray-400 text-sm">Failed to load image</p>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, defineProps } from 'vue';

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    alt: {
        type: String,
        default: 'photo',
    },
    imageClass: {
        type: String,
        default: 'w-full h-full object-contain',
    },
});

const isLoading = ref(true);
const hasError = ref(false);

// Watch for src changes and reset loading state
watch(
    () => props.src,
    (newSrc) => {
        if (newSrc) {
            isLoading.value = true;
            hasError.value = false;
        }
    }
);

const onImageLoad = () => {
    isLoading.value = false;
    hasError.value = false;
};

const onImageError = () => {
    isLoading.value = false;
    hasError.value = true;
};
</script>
