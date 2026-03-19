<template>
    <div class="flex flex-col items-center justify-center">
        <label class="relative inline-flex cursor-pointer items-center">
            <!-- Hidden native checkbox -->
            <input id="switch" type="checkbox" class="peer sr-only" :checked="modelValue" @change="toggleSwitch" />
            <!-- (Optional) Hidden label for accessibility if needed -->
            <label for="switch" class="hidden"></label>
            <!-- Switch track and thumb -->
            <div
                class="peer h-6 w-11 rounded-full border bg-slate-500 after:absolute after:left-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-green-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:ring-green-300"
            ></div>
        </label>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

function toggleSwitch(event) {
    emit('update:modelValue', event.target.checked);
}
</script>

<style scoped>
/* Additional component-specific styles can go here */
</style>
