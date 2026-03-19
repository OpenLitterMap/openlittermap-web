<template>
    <div class="pagination-controls">
        <button
            @click="$emit('previous')"
            :disabled="!canLoadPrevious || isLoading"
            class="pagination-btn"
            :class="{ disabled: !canLoadPrevious || isLoading }"
        >
            <i v-if="!isLoading" class="fas fa-chevron-left"></i>
            <i v-else class="fas fa-spinner fa-spin"></i>
            Previous
        </button>

        <span class="pagination-info"> Page {{ currentPage }} of {{ totalPages }} </span>

        <button
            @click="$emit('next')"
            :disabled="!canLoadNext || isLoading"
            class="pagination-btn"
            :class="{ disabled: !canLoadNext || isLoading }"
        >
            Next
            <i v-if="!isLoading" class="fas fa-chevron-right"></i>
            <i v-else class="fas fa-spinner fa-spin"></i>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true,
    },
    totalPages: {
        type: Number,
        required: true,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['previous', 'next']);

const canLoadPrevious = computed(() => props.currentPage > 1);
const canLoadNext = computed(() => props.currentPage < props.totalPages);
</script>

<style scoped>
.pagination-controls {
    position: absolute;
    bottom: 20px;
    right: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.95);
    padding: 10px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    z-index: 1000;
}

.pagination-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    background: #14d145;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination-btn:hover:not(.disabled) {
    background: #12b83d;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(20, 209, 69, 0.3);
}

.pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #ccc;
}

.pagination-info {
    font-size: 14px;
    font-weight: 500;
    color: #333;
    min-width: 100px;
    text-align: center;
}

/* Mobile responsiveness */
@media (max-width: 640px) {
    .pagination-controls {
        bottom: 10px;
        right: 10px;
        padding: 8px 12px;
        gap: 10px;
    }

    .pagination-btn {
        padding: 6px 12px;
        font-size: 12px;
    }

    .pagination-info {
        font-size: 12px;
        min-width: 80px;
    }
}
</style>
