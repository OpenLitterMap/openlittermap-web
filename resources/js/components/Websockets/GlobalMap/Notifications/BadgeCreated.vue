<template>
    <div class="bg-white rounded shadow p-4 cursor-pointer hover:shadow-lg mb-2">
        <div class="font-bold text-lg mb-2">New Badge Unlocked!</div>

        <img :src="payload.badge.full_path" alt="Generated Image" class="w-full h-auto rounded mb-2 object-cover" />
        <p class="text-gray-600">
            {{ `A new ${payload.badge.subtype} badge has been generated.` }}
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
