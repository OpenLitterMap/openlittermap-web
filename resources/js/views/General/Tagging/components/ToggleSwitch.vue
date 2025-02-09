<template>
    <label class="relative inline-flex items-center cursor-pointer">
        <!-- Hidden native checkbox -->
        <input type="checkbox" class="sr-only peer" :checked="modelValue" @change="onChange" />

        <!-- The switch track and thumb -->
        <div
            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 dark:peer-focus:ring-blue-800 rounded-full dark:bg-gray-700 peer-checked:bg-blue-600 transition-colors duration-200 ease-in-out"
        />

        <!-- Optional label -->
        <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">
            {{ label }}
        </span>
    </label>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

function onChange(event) {
    emit('update:modelValue', event.target.checked);
}
</script>

<style scoped>
/* Optional additional styling can go here */
</style>
