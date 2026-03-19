<template>
    <div class="vld-parent">
        <loading
            :active="active"
            :can-cancel="canCancel"
            :on-cancel="onCancel"
            :is-full-page="isFullPage"
            :color="color"
            :background="background"
        />
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import Loading from 'vue-loading-overlay';
import 'vue-loading-overlay/dist/css/index.css';

defineProps({
    active: {
        type: Boolean,
        required: true,
    },
    canCancel: {
        type: Boolean,
        default: false,
    },
    isFullPage: {
        type: Boolean,
        default: true,
    },
    color: {
        type: String,
        default: '#007BFF', // Default to a blue color
    },
    background: {
        type: String,
        default: 'rgba(255, 255, 255, 0.8)',
    },
});

const emit = defineEmits(['cancel']);

const onCancel = () => {
    emit('cancel');
};
</script>

<style scoped>
</style>
