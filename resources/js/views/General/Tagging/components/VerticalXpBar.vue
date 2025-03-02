<template>
    <div class="absolute h-2/3 w-[5em] mx-4">
        <div class="h-full flex flex-col items-center space-y-1">
            <!-- Level label -->
            <div class="text-center text-green-700 font-bold text-sm">Level 1</div>

            <!-- Vertical progress bar container -->
            <div class="relative h-full w-3 bg-gray-200 rounded-full dark:bg-gray-700 flex flex-col-reverse">
                <!-- Filled portion (progress) -->
                <div class="bg-blue-600 w-full rounded-full" :style="{ height: xpProgress + '%' }"></div>

                <!-- Horizontal marker line at current percentage -->
                <div
                    class="absolute left-0 w-full border-t-2 border-red-500"
                    :style="{ bottom: xpProgress + '%' }"
                ></div>

                <!-- Centered percentage label -->
                <div
                    class="absolute left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-xs font-bold text-red-500"
                    :style="{
                        bottom: `calc(${xpProgress}% - 1.25em)`,
                        left: '-1.5em',
                    }"
                >
                    {{ xpProgress }}%
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const xpProgress = ref(45);
</script>

<style scoped></style>
