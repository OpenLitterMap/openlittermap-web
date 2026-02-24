<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-slate-600">
                <strong>{{ stats.pending }}</strong> photos awaiting your review.
                Approved photos will appear on the public map, attributed to your school.
            </p>

            <button
                v-if="stats.pending > 0"
                :disabled="approving"
                class="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                @click="approveAll"
            >
                {{ approving ? 'Approving...' : `Approve All (${stats.pending})` }}
            </button>
        </div>

        <!-- Success message -->
        <div
            v-if="successMessage"
            class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"
        >
            {{ successMessage }}
        </div>

        <!-- Empty -->
        <div v-if="!loading && photos.data.length === 0" class="text-center py-12">
            <div class="text-4xl mb-3">&#10003;</div>
            <p class="text-slate-500 font-medium">All caught up!</p>
            <p class="text-sm text-slate-400 mt-1">No photos pending approval.</p>
        </div>

        <!-- Photo cards -->
        <div v-else class="space-y-3">
            <div
                v-for="photo in photos.data"
                :key="photo.id"
                class="bg-white rounded-xl shadow-sm p-4 flex gap-4"
            >
                <!-- Thumbnail -->
                <div class="w-24 h-24 shrink-0 rounded-lg bg-slate-100 overflow-hidden">
                    <img
                        v-if="photo.filename"
                        :src="`/storage/photos/${photo.filename}`"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                </div>

                <!-- Details -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-700">
                                {{ photo.photo_tags?.length || 0 }} tags
                                &middot;
                                {{ photo.total_tags || 0 }} items
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                by {{ photo.user?.name || 'Unknown' }}
                                &middot;
                                {{ formatDate(photo.created_at) }}
                            </p>
                        </div>

                        <div class="flex gap-2 shrink-0">
                            <button
                                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50"
                                @click="editPhoto(photo)"
                            >
                                Edit Tags
                            </button>
                            <button
                                :disabled="approving"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
                                @click="approveOne(photo.id)"
                            >
                                Approve
                            </button>
                        </div>
                    </div>

                    <!-- Tag summary -->
                    <div class="flex flex-wrap gap-1 mt-2">
                        <span
                            v-for="tag in (photo.photo_tags || []).slice(0, 6)"
                            :key="tag.id"
                            class="px-2 py-0.5 text-xs bg-slate-100 text-slate-600 rounded"
                        >
                            {{ tag.object }} &times;{{ tag.quantity }}
                        </span>
                        <span
                            v-if="(photo.photo_tags || []).length > 6"
                            class="px-2 py-0.5 text-xs bg-slate-100 text-slate-400 rounded"
                        >
                            +{{ photo.photo_tags.length - 6 }} more
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="photos.last_page > 1" class="flex justify-center gap-3 mt-6">
            <button
                :disabled="photos.current_page <= 1"
                class="px-4 py-2 text-sm rounded-lg border border-slate-300 bg-white disabled:opacity-40"
                @click="changePage(photos.current_page - 1)"
            >
                Previous
            </button>
            <span class="px-3 py-2 text-sm text-slate-500">
                {{ photos.current_page }} / {{ photos.last_page }}
            </span>
            <button
                :disabled="photos.current_page >= photos.last_page"
                class="px-4 py-2 text-sm rounded-lg border border-slate-300 bg-white disabled:opacity-40"
                @click="changePage(photos.current_page + 1)"
            >
                Next
            </button>
        </div>

        <!-- Edit modal -->
        <TeamPhotoEdit
            v-if="editingPhoto"
            :photo="editingPhoto"
            :is-leader="true"
            :is-school-team="true"
            @close="editingPhoto = null"
            @saved="onEditSaved"
        />
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useTeamPhotosStore } from '@/stores/teamPhotos';
import TeamPhotoEdit from './TeamPhotoEdit.vue';

export default {
    name: 'TeamApprovalQueue',
    components: { TeamPhotoEdit },
    props: {
        teamId: { type: Number, required: true },
    },
    setup(props) {
        const store = useTeamPhotosStore();
        const editingPhoto = ref(null);
        const successMessage = ref('');

        const photos = computed(() => store.photos);
        const stats = computed(() => store.stats);
        const loading = computed(() => store.loading);
        const approving = computed(() => store.approving);

        const approveOne = async (photoId) => {
            const count = await store.approvePhotos(props.teamId, [photoId]);
            if (count > 0) {
                showSuccess(`${count} photo approved and published.`);
            }
        };

        const approveAll = async () => {
            if (!confirm(`Approve all ${stats.value.pending} pending photos? They will be published to the global map.`)) return;

            const count = await store.approvePhotos(props.teamId);
            if (count > 0) {
                showSuccess(`${count} photos approved and published.`);
            }
        };

        const editPhoto = (photo) => {
            editingPhoto.value = photo;
        };

        const onEditSaved = () => {
            editingPhoto.value = null;
            store.fetchPhotos(props.teamId, photos.value.current_page);
        };

        const changePage = (page) => store.fetchPhotos(props.teamId, page);

        const showSuccess = (msg) => {
            successMessage.value = msg;
            setTimeout(() => { successMessage.value = ''; }, 4000);
        };

        const formatDate = (date) => {
            return new Intl.DateTimeFormat('en-IE', {
                month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
            }).format(new Date(date));
        };

        onMounted(() => {
            store.setFilter('pending');
            store.fetchPhotos(props.teamId);
        });

        return {
            photos, stats, loading, approving, editingPhoto, successMessage,
            approveOne, approveAll, editPhoto, onEditSaved, changePage, formatDate,
        };
    },
};
</script>
