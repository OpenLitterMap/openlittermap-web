<template>
    <div class="bg-gray-800 rounded-lg p-4">
        <div class="relative">
            <!-- Empty state: no photo available -->
            <div v-if="!photoSrc && !loading" class="bg-gray-700 rounded-lg aspect-video flex flex-col items-center justify-center">
                <svg class="w-16 h-16 text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
                <div class="text-gray-500 text-sm">Your image will appear here for tagging</div>
            </div>

            <!-- Loading skeleton -->
            <div v-else-if="loading" class="animate-pulse">
                <div class="bg-gray-700 rounded-lg aspect-video flex flex-col items-center justify-center">
                    <svg class="w-16 h-16 text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    <div class="text-gray-500 text-sm">Loading photo...</div>
                </div>
            </div>

            <!-- Actual image -->
            <img
                v-show="!loading && !error && photoSrc"
                :src="photoSrc"
                @load="handleLoad"
                @error="handleError"
                alt="Photo to tag"
                class="w-full h-auto rounded-lg cursor-zoom-in hover:opacity-95 transition-opacity"
                @click="toggleZoom"
            />

            <!-- Error state -->
            <div
                v-if="error && !loading"
                class="bg-gray-700 rounded-lg aspect-video flex flex-col items-center justify-center"
            >
                <svg class="w-16 h-16 text-red-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-gray-400">Failed to load image</p>
            </div>
        </div>

        <!-- Zoom overlay -->
        <div
            v-if="zoomed"
            @click="toggleZoom"
            class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center cursor-zoom-out"
        >
            <img :src="photoSrc" alt="Zoomed photo" class="max-w-[90vw] max-h-[90vh] object-contain" />
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    photoSrc: String,
    loading: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['image-loaded']);

const error = ref(false);
const zoomed = ref(false);

watch(
    () => props.photoSrc,
    () => {
        error.value = false;
    }
);

const handleLoad = () => {
    error.value = false;
    emit('image-loaded');
};

const handleError = () => {
    error.value = true;
    emit('image-loaded');
};

const toggleZoom = () => {
    if (!error.value && props.photoSrc) {
        zoomed.value = !zoomed.value;
    }
};
</script>
