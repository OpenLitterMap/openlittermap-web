<template>
    <div class="bg-gray-100 p-5 rounded-md mb-10 flex justify-evenly">
        <span class="text-center">XP to level up: <br />69</span>

        <div class="flex justify-center items-center min-w-[15em]">
            <p>{{ remainingPhotos }} untagged photos</p>
        </div>

        <div class="text-center">
            <p>Team:</p>
            <span class="font-bold">Cleanup</span>
        </div>

        <div class="flex">
            <button
                class="p-2 rounded bg-green-500 w-[5em]"
                :disabled="!newTags.length"
                :class="!newTags.length ? 'opacity-50 cursor-not-allowed' : ''"
                v-tooltip="!newTags.length ? 'Please add a tag' : ''"
                @click="submit"
            >
                Submit
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePhotosStore } from '@/stores/photos';
const photosStore = usePhotosStore();

const props = defineProps({
    newTags: {
        type: Array,
        required: true,
    },
    photoId: {
        type: Number,
        required: true,
    },
});

const isUploading = ref(false);
const remainingPhotos = computed(() => photosStore.remaining);

const prepareTagsForUpload = () => {
    return props.newTags.value.map((tag) => {
        const materials = tag.extraTags
            .filter((extraTag) => extraTag.selected && extraTag.type === 'material')
            .map((extraTag) => {
                return {
                    id: extraTag.id,
                    key: extraTag.key,
                };
            });

        const custom_tags = tag.extraTags
            .filter((extraTag) => extraTag.selected && extraTag.type === 'custom')
            .map((extraTag) => extraTag.key);

        return {
            category: { id: tag.category.id, key: tag.category.key },
            object: { id: tag.object.id, key: tag.object.key },
            quantity: tag.quantity,
            picked_up: tag.pickedUp,
            materials,
            custom_tags,
        };
    });
};

const submit = async () => {
    isUploading.value = true;

    const tags = prepareTagsForUpload();

    await photosStore.UPLOAD_TAGS({
        photoId: props.photoId,
        tags,
    });

    isUploading.value = false;
};
</script>

<style scoped></style>
