<template>
    <div class="bg-white rounded shadow p-4 cursor-pointer hover:shadow-lg">
        <div class="font-bold text-lg mb-2">New Image Created!</div>

        <img :src="payload.full_path" alt="Generated Image" class="w-full h-auto rounded mb-2 object-cover" />
        <p class="text-gray-600">
            {{ 'A new image has been generated and is now available.' }}
        </p>
    </div>
</template>

<script>
export default {
    name: 'ImageCreatedNotification',
    props: {
        payload: {
            type: Object,
            required: true,
        },
    },
};
</script>
