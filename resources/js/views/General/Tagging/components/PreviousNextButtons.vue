<template>
    <div class="flex justify-center gap-6 mt-8">
        <span class="flex cursor-pointer hover:text-gray-300" @click="previous">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-6 mr-4"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18" />
            </svg>

            Previous
        </span>
        <span class="flex cursor-pointer hover:text-gray-300" @click="next">
            Next
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-6 ml-4"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
            </svg>
        </span>
    </div>
</template>

<script setup>
const previous = () => {
    console.log('Previous');
};

const next = () => {
    console.log('Next');
};
</script>

<style scoped></style>
