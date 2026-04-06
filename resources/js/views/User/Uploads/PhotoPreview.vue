<template>
    <div class="photo-preview">
        <img
            v-if="!imageError"
            :src="imageSrc"
            :alt="`Photo ${photoId}`"
            @error="handleImageError"
            @load="imageLoaded = true"
            :class="{ loaded: imageLoaded }"
        />
        <div v-else class="image-error">
            <span class="error-icon">🖼️</span>
            <span>Unable to load image</span>
            <span class="error-filename">{{ fileName }}</span>
        </div>
        <div v-if="!imageError && !imageLoaded" class="image-loading">
            <span>Loading image...</span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { resolvePhotoUrl } from '@/composables/usePhotoUrl';

const props = defineProps({
    filename: {
        type: String,
        required: true,
    },
    photoId: {
        type: [Number, String],
        required: true,
    },
});

const imageError = ref(false);
const imageLoaded = ref(false);

const imageSrc = computed(() => resolvePhotoUrl(props.filename));

const fileName = computed(() => {
    if (!props.filename) return 'No filename';
    const parts = props.filename.split('/');
    return parts[parts.length - 1] || props.filename;
});

const handleImageError = () => {
    imageError.value = true;
    imageLoaded.value = false;
};
</script>

<style scoped>
.photo-preview {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f8f8;
    position: relative;
}

.photo-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.photo-preview img.loaded {
    opacity: 1;
}

.image-error {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: #666;
    padding: 20px;
    text-align: center;
}

.error-icon {
    font-size: 48px;
    opacity: 0.3;
}

.error-filename {
    font-size: 12px;
    font-family: monospace;
    color: #999;
    word-break: break-all;
    max-width: 250px;
}

.image-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #999;
    font-size: 14px;
}
</style>
