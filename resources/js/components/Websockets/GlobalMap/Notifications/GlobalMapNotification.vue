<template>
    <div
        class="flex cursor-pointer rounded-md mb-2 pr-4"
        :class="colorClass"
    >
        <!-- Image Slot -->
        <div class="p-4 my-auto">
            <slot name="image"></slot>
        </div>
        <!-- Content Slot -->
        <div class="py-3">
            <slot name="content"></slot>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Define the props
 */
const props = defineProps({
    color: {
        type: String,
        default: 'green',
    },
})

/**
 * Computes which class to apply based on the `color` prop.
 * These classes are defined in the <style> block below.
 */
const colorClass = computed(() => {
    switch (props.color) {
        case 'blue':
            return 'bg-blue'
        case 'yellow':
            return 'bg-yellow'
        case 'colourful':
            return 'bg-colourful'
        case 'littercoin':
            return 'bg-littercoin'
        default:
            return 'bg-green' // Fallback: green
    }
})
</script>

<style scoped>
    /* Basic background colors */
    .bg-green {
        background-color: #88d267;
    }
    .bg-blue {
        background-color: #4bb0e0;
    }
    .bg-yellow {
        background-color: #f1c40f;
    }

    /* Animated gradients */
    .bg-colourful {
        background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
        background-size: 400% 400%;
        animation: gradient 15s ease infinite;
    }
    .bg-littercoin {
        background: linear-gradient(-45deg, #2ecc71, #f1c40f, #f68739);
        background-size: 400% 400%;
        animation: gradient 5s ease infinite;
    }

    /* Keyframes for the gradient animation */
    @keyframes gradient {
        0% {
            background-position: 0 50%;
        }
        50% {
            background-position: 100% 50%;
        }
        100% {
            background-position: 0 50%;
        }
    }
</style>
