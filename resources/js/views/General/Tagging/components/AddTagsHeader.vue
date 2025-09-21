<template>
    <div class="bg-gray-100 p-5 rounded-md mb-10 flex justify-evenly">
        <div>
            <p class="text-center">Photo #{{ photo?.id ? photo.id : '' }}</p>

            <p class="mt-2 text-center">Taken at: <br />{{ getDate() }}</p>
        </div>

        <div class="flex justify-center items-center min-w-[15em]">
            <PreviousNextButtons
                :paginatedPhotos="paginatedPhotos"
                :remainingPhotos="remainingPhotos"
                :currentPhotoPage="currentPhotoPage"
                @previous="loadPreviousPhoto"
                @next="loadNextPhoto"
            />
        </div>

        <div class="text-center">
            <p>Team:</p>
            <p class="font-bold mt-2">{{ photo?.team?.name ? photo?.team?.name : 'None' }}</p>
        </div>

        <div class="flex flex-col justify-center">
            <button
                class="p-1 rounded bg-green-500 w-[6em]"
                :disabled="!newTags.length"
                :class="!newTags.length ? 'opacity-50 cursor-not-allowed' : ''"
                v-tooltip="!newTags.length ? 'Please add a tag' : ''"
                @click="submit"
            >
                <span v-if="!isUploading">Submit</span>

                <Spinner v-else class="text-center" />
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, defineEmits, ref } from 'vue';
import { usePhotosStore } from '../../../../stores/photos/index.js';
import PreviousNextButtons from './PreviousNextButtons.vue';
import Spinner from '../../../../components/Loading/Spinner.vue';
import moment from 'moment';

const photosStore = usePhotosStore();
const remainingPhotos = computed(() => photosStore.remaining);
const currentPhotoPage = computed(() => photosStore.paginated?.current_page);

const props = defineProps({
    newTags: {
        type: Array,
        required: true,
    },
    paginatedPhotos: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['clearTags']);

const loadPreviousPhoto = async () => {
    if (photosStore.paginated?.prev_page_url) {
        await photosStore.GET_USERS_UNTAGGED_PHOTOS(photosStore.paginated.current_page - 1);
    }
};

const loadNextPhoto = async () => {
    if (photosStore.paginated?.next_page_url) {
        await photosStore.GET_USERS_UNTAGGED_PHOTOS(photosStore.paginated.current_page + 1);
    }
};

const isUploading = ref(false);
const photo = computed(() => props.paginatedPhotos?.data[0]);

const getDate = () => {
    return moment(props.paginatedPhotos?.data[0]?.datetime).format('LLL');
};

const prepareTagsForUpload = () => {
    return props.newTags.map((tag) => {
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

        // Check if the parent-level tag is a custom tag
        if (tag.hasOwnProperty('custom') && tag.custom) {
            return {
                custom: true,
                key: tag.key,
                picked_up: tag.pickedUp,
                quantity: tag.quantity,
                materials,
                custom_tags,
            };
        } else {
            return {
                category: { id: tag.category.id, key: tag.category.key },
                object: { id: tag.object.id, key: tag.object.key },
                quantity: tag.quantity,
                picked_up: tag.pickedUp,
                materials,
                custom_tags,
            };
        }
    });
};

const submit = async () => {
    isUploading.value = true;

    const tags = prepareTagsForUpload();

    await photosStore.UPLOAD_TAGS({
        photoId: photo.value.id,
        tags,
    });

    isUploading.value = false;

    emit('clearTags');
};
</script>

<style scoped></style>
