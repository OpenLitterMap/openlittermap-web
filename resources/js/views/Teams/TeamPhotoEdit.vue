<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="$emit('close')">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto mx-4">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-800">
                    {{ isLeader && isSchoolTeam ? 'Review & Edit Tags' : 'Photo Details' }}
                </h2>
                <button class="p-1 text-slate-400 hover:text-slate-600" @click="$emit('close')">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Photo -->
            <div class="px-6 py-4">
                <div class="aspect-video bg-slate-100 rounded-lg overflow-hidden mb-4">
                    <img
                        v-if="photo.filename"
                        :src="resolvePhotoUrl(photo.filename)"
                        class="w-full h-full object-contain"
                    />
                </div>

                <!-- Meta -->
                <div class="flex gap-4 text-sm text-slate-500 mb-4">
                    <span>Photo #{{ photo.id }}</span>
                    <span>{{ formatDate(photo.created_at) }}</span>
                    <span v-if="photo.user">by {{ photo.user.name }}</span>
                </div>
            </div>

            <!-- Tags editor -->
            <div class="px-6 pb-4">
                <h3 class="text-sm font-medium text-slate-700 mb-3">Tags</h3>

                <div v-if="canEdit" class="bg-gray-900 rounded-xl p-4 space-y-4 text-white">
                    <UnifiedTagSearch
                        v-model="searchQuery"
                        :tags="searchableTags"
                        :brands="tagsStore.brands"
                        :materials="tagsStore.materials"
                        @tag-selected="handleTagSelection"
                        @custom-tag="handleCustomTag"
                    />
                    <ActiveTagsList
                        :tags="editTags"
                        :searchable-tags="searchableTags"
                        :brands="tagsStore.brands"
                        :materials="tagsStore.materials"
                        @update-quantity="updateTagQuantity"
                        @set-picked-up="setPickedUp"
                        @set-type="setTagType"
                        @add-detail="addTagDetail"
                        @remove-tag="removeTag"
                        @remove-detail="removeTagDetail"
                    />
                </div>
                <ul v-else class="space-y-2 text-sm text-slate-700">
                    <li v-for="tag in editTags" :key="tag.id">
                        {{ tag.quantity }} × {{ tag.object?.key || tag.brand?.key || tag.material?.key || tag.key }}
                        <span v-if="tag.typeKey">({{ tag.typeKey }})</span>
                    </li>
                </ul>

                <!-- Errors -->
                <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
            </div>

            <!-- Footer -->
            <div class="flex justify-between px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
                <button
                    v-if="canEdit"
                    :disabled="deleting"
                    class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 disabled:opacity-50"
                    @click="deletePhoto"
                >
                    {{ deleting ? 'Deleting...' : 'Delete Photo' }}
                </button>
                <div v-else></div>

                <div class="flex gap-3">
                    <button
                        class="px-4 py-2 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100"
                        @click="$emit('close')"
                    >
                        {{ canEdit ? 'Cancel' : 'Close' }}
                    </button>
                    <button
                        v-if="canEdit"
                        :disabled="saving || !ready"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                        @click="save"
                    >
                        {{ saving ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useTeamPhotosStore } from '@/stores/teamPhotos';
import { useTagsStore } from '@/stores/tags/index.js';
import {
    addCardDetail,
    buildSearchableTags,
    cardFromCustomTag,
    cardFromSelection,
    cardsFromApiTags,
    payloadFromCards,
    removeCardDetail,
    setCardQuantity,
    setCardType,
} from '@/composables/useTagEditorState.js';
import UnifiedTagSearch from '@/views/General/Tagging/v2/components/UnifiedTagSearch.vue';
import ActiveTagsList from '@/views/General/Tagging/v2/components/ActiveTagsList.vue';
import { resolvePhotoUrl } from '@/composables/usePhotoUrl';

const props = defineProps({
    photo: { type: Object, required: true },
    teamId: { type: Number, required: true },
    isLeader: { type: Boolean, default: false },
    isSchoolTeam: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'saved', 'deleted']);

const store = useTeamPhotosStore();
const tagsStore = useTagsStore();
const searchQuery = ref('');
const editTags = ref([]);
const saving = ref(false);
const ready = ref(false);
const deleting = ref(false);
const error = ref('');

const canEdit = computed(() => props.isLeader && props.isSchoolTeam);
const searchableTags = computed(() => buildSearchableTags(tagsStore));

onMounted(async () => {
    try {
        if (!tagsStore.objects.length) await tagsStore.GET_ALL_TAGS();
        editTags.value = cardsFromApiTags(props.photo.new_tags, tagsStore);
        ready.value = true;
    } catch (e) {
        error.value = e.message || 'Unable to load tags.';
    }
});

const findCard = (tagId) => editTags.value.find((tag) => tag.id === tagId);

const handleTagSelection = (selected) => {
    const card = selected?.raw ? cardFromSelection(selected, tagsStore) : null;
    if (card) editTags.value.push(card);
};
const handleCustomTag = (customTag) => editTags.value.push(cardFromCustomTag(customTag));
const updateTagQuantity = (tagId, quantity) => {
    const tag = findCard(tagId);
    if (tag) setCardQuantity(tag, quantity);
};
const setPickedUp = (tagId, value) => {
    const tag = findCard(tagId);
    if (tag) tag.pickedUp = value;
};
const setTagType = (tagId, typeId) => {
    const tag = findCard(tagId);
    if (tag) setCardType(tag, typeId, tagsStore);
};
const addTagDetail = (tagId, detail) => {
    const tag = findCard(tagId);
    if (tag) addCardDetail(tag, detail);
};
const removeTagDetail = (tagId, detail) => {
    const tag = findCard(tagId);
    if (tag) removeCardDetail(tag, detail);
};
const removeTag = (tagId) => {
    editTags.value = editTags.value.filter((tag) => tag.id !== tagId);
};

const save = async () => {
    error.value = '';

    const validTags = payloadFromCards(editTags.value);
    if (validTags.length === 0) {
        error.value = 'At least one valid tag is required.';
        return;
    }

    saving.value = true;
    const success = await store.updateTags(props.photo.id, validTags);
    saving.value = false;

    if (success) {
        emit('saved');
    } else {
        error.value = 'Failed to save. Please try again.';
    }
};

const deletePhoto = async () => {
    if (!confirm('Delete this photo? This cannot be undone.')) return;

    deleting.value = true;
    const success = await store.deletePhoto(props.teamId, props.photo.id);
    deleting.value = false;

    if (success) {
        emit('deleted');
    } else {
        error.value = 'Failed to delete photo.';
    }
};

const formatDate = (date) =>
    new Intl.DateTimeFormat('en-IE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
</script>
