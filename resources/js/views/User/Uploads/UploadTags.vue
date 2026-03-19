<template>
    <div class="modal-overlay" @click="emit('close')">
        <div class="modal-content" @click.stop>
            <div class="modal-header">
                <div class="header-info">
                    <h2>Tag Details - Photo #{{ photo.id }}</h2>
                    <span class="photo-filename">{{ getFileName(photo.filename) }}</span>
                </div>
                <button class="close-btn" @click="emit('close')">×</button>
            </div>

            <div class="modal-layout">
                <!-- Photo Preview Section -->
                <div class="photo-sidebar">
                    <PhotoPreview :filename="photo.filename" :photo-id="photo.id" />
                </div>

                <!-- Content Section -->
                <div class="modal-right">
                    <div class="modal-tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            class="tab-btn"
                            :class="{ active: activeTab === tab.key }"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="modal-body">
                        <!-- Tagged View -->
                        <TaggedView v-if="activeTab === 'tagged'" :photo="photo" />

                        <!-- Summary View -->
                        <TagSummaryTab v-if="activeTab === 'summary'" :summary="photo.summary" />

                        <!-- Raw View -->
                        <RawDataView v-if="activeTab === 'raw'" :old-tags="photo.old_tags" :new-tags="photo.new_tags" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import PhotoPreview from './PhotoPreview.vue';
import TaggedView from './TaggedView.vue';
import TagSummaryTab from './TagSummaryTab.vue';
import RawDataView from './RawDataView.vue';

const props = defineProps({
    photo: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const activeTab = ref('tagged');
const tabs = [
    { key: 'tagged', label: 'Tagged View' },
    { key: 'summary', label: 'Summary' },
    { key: 'raw', label: 'Raw Data' },
];

// Extract filename from path
const getFileName = (path) => {
    if (!path) return 'No filename';
    const parts = path.split('/');
    return parts[parts.length - 1] || path;
};

// Handle ESC key
const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    document.body.style.overflow = 'hidden';
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 1200px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.header-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.modal-header h2 {
    margin: 0;
    font-size: 20px;
    color: #333;
}

.photo-filename {
    font-size: 13px;
    color: #666;
    font-family: 'Monaco', 'Menlo', monospace;
}

.close-btn {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #666;
}

.close-btn:hover {
    background: #f0f0f0;
    color: #333;
}

.modal-layout {
    display: flex;
    flex: 1;
    overflow: hidden;
}

.photo-sidebar {
    width: 40%;
    border-right: 1px solid #e0e0e0;
    padding: 20px;
}

.modal-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-tabs {
    display: flex;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f8f8;
}

.tab-btn {
    flex: 1;
    padding: 12px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    color: #666;
    transition: all 0.2s;
}

.tab-btn:hover {
    background: #f0f0f0;
}

.tab-btn.active {
    background: white;
    color: #333;
    font-weight: 600;
    border-bottom: 2px solid #2196f3;
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

@media (max-width: 768px) {
    .modal-layout {
        flex-direction: column;
    }

    .photo-sidebar {
        width: 100%;
        height: 200px;
        border-right: none;
        border-bottom: 1px solid #e0e0e0;
        padding: 10px;
    }

    .modal-content {
        width: 95%;
        max-height: 90vh;
        max-width: 100%;
    }
}
</style>
