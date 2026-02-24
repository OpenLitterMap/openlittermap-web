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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Photo -->
            <div class="px-6 py-4">
                <div class="aspect-video bg-slate-100 rounded-lg overflow-hidden mb-4">
                    <img
                        v-if="photo.filename"
                        :src="`/storage/photos/${photo.filename}`"
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

                <div class="space-y-2">
                    <div
                        v-for="(tag, index) in editTags"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="tag.category"
                            type="text"
                            placeholder="Category"
                            class="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg"
                            :disabled="!canEdit"
                        />
                        <input
                            v-model="tag.object"
                            type="text"
                            placeholder="Object"
                            class="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg"
                            :disabled="!canEdit"
                        />
                        <input
                            v-model.number="tag.quantity"
                            type="number"
                            min="1"
                            class="w-20 px-3 py-2 text-sm border border-slate-300 rounded-lg text-center"
                            :disabled="!canEdit"
                        />
                        <label class="flex items-center gap-1 text-xs text-slate-500">
                            <input
                                v-model="tag.picked_up"
                                type="checkbox"
                                :disabled="!canEdit"
                            />
                            Picked up
                        </label>
                        <button
                            v-if="canEdit"
                            class="p-1 text-red-400 hover:text-red-600"
                            @click="removeTag(index)"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    v-if="canEdit"
                    class="mt-3 px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                    @click="addTag"
                >
                    + Add tag
                </button>

                <!-- Errors -->
                <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
                <button
                    class="px-4 py-2 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100"
                    @click="$emit('close')"
                >
                    {{ canEdit ? 'Cancel' : 'Close' }}
                </button>
                <button
                    v-if="canEdit"
                    :disabled="saving"
                    class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                    @click="save"
                >
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useTeamPhotosStore } from '@/stores/teamPhotos';

export default {
    name: 'TeamPhotoEdit',
    props: {
        photo: { type: Object, required: true },
        isLeader: { type: Boolean, default: false },
        isSchoolTeam: { type: Boolean, default: false },
    },
    emits: ['close', 'saved'],
    setup(props, { emit }) {
        const store = useTeamPhotosStore();
        const editTags = ref([]);
        const saving = ref(false);
        const error = ref('');

        const canEdit = computed(() => props.isLeader && props.isSchoolTeam);

        onMounted(() => {
            // Deep clone tags for editing
            editTags.value = (props.photo.photo_tags || []).map((t) => ({
                id: t.id,
                category: t.category,
                object: t.object,
                quantity: t.quantity,
                picked_up: !!t.picked_up,
            }));
        });

        const addTag = () => {
            editTags.value.push({
                id: null,
                category: '',
                object: '',
                quantity: 1,
                picked_up: false,
            });
        };

        const removeTag = (index) => {
            editTags.value.splice(index, 1);
        };

        const save = async () => {
            error.value = '';

            // Validate
            const validTags = editTags.value.filter((t) => t.category && t.object && t.quantity > 0);
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

        const formatDate = (date) => {
            return new Intl.DateTimeFormat('en-IE', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit',
            }).format(new Date(date));
        };

        return {
            editTags, saving, error, canEdit,
            addTag, removeTag, save, formatDate,
        };
    },
};
</script>
