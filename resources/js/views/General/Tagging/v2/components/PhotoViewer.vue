<template>
    <div class="bg-white/5 border border-white/10 rounded-xl p-4 h-full flex items-center justify-center overflow-hidden">
        <div class="relative w-full h-full flex items-center justify-center">
            <!-- Delete button -->
            <button
                v-if="resolvedSrc && !loading && !error"
                @click.stop="emit('delete')"
                :disabled="deleting"
                class="absolute top-2 right-2 z-10 bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg p-2 transition-colors shadow-lg"
                title="Delete photo"
            >
                <svg v-if="!deleting" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </button>

            <!-- Empty state: no photo available -->
            <div v-if="!resolvedSrc && !loading" class="bg-white/5 border border-white/10 rounded-lg aspect-video flex flex-col items-center justify-center">
                <svg class="w-16 h-16 text-white/10 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
                <div class="text-white/30 text-sm">Your image will appear here for tagging</div>
            </div>

            <!-- Loading skeleton -->
            <div v-else-if="loading" class="animate-pulse">
                <div class="bg-white/10 rounded-lg aspect-video flex flex-col items-center justify-center">
                    <svg class="w-16 h-16 text-white/10 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    <div class="text-white/30 text-sm">Loading photo...</div>
                </div>
            </div>

            <!-- Actual image -->
            <img
                v-show="!loading && !error && resolvedSrc"
                :src="resolvedSrc"
                @load="handleLoad"
                @error="handleError"
                alt="Photo to tag"
                class="max-w-full max-h-full object-contain rounded-lg cursor-zoom-in hover:opacity-95 transition-opacity"
                @click="toggleZoom"
            />

            <!-- Error state -->
            <div
                v-if="error && !loading"
                class="bg-white/5 border border-white/10 rounded-lg aspect-video flex flex-col items-center justify-center"
            >
                <svg class="w-16 h-16 text-red-400/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-white/40">Failed to load image</p>
            </div>
        </div>

        <!-- Zoom overlay -->
        <div
            v-if="zoomed"
            @click="toggleZoom"
            class="fixed inset-0 z-50 bg-black/90 backdrop-blur-sm flex items-center justify-center cursor-zoom-out"
        >
            <img :src="resolvedSrc" alt="Zoomed photo" class="max-w-[90vw] max-h-[90vh] object-contain" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { resolvePhotoUrl } from '@/composables/usePhotoUrl';

const props = defineProps({
    photoSrc: String,
    loading: {
        type: Boolean,
        default: true,
    },
    deleting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['image-loaded', 'delete']);

const resolvedSrc = computed(() => props.photoSrc ? resolvePhotoUrl(props.photoSrc) : null);

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
    if (!error.value && resolvedSrc.value) {
        zoomed.value = !zoomed.value;
    }
};
</script>
