<template>
    <div>
        <!-- Filter tabs -->
        <div class="flex gap-2 mb-4">
            <button
                v-for="f in filters"
                :key="f.value"
                class="px-3 py-1.5 text-sm rounded-lg transition-colors"
                :class="filter === f.value
                    ? 'bg-blue-100 text-blue-700 font-medium'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="setFilter(f.value)"
            >
                {{ f.label }}
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12 text-slate-500">Loading photos...</div>

        <!-- Empty state -->
        <div v-else-if="photos.data.length === 0" class="text-center py-12">
            <p class="text-slate-500">No photos found.</p>
            <p class="text-sm text-slate-500 mt-1">Upload photos with this team set as active to see them here.</p>
        </div>

        <!-- Photo grid -->
        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div
                v-for="photo in photos.data"
                :key="photo.id"
                class="bg-white rounded-xl shadow-sm overflow-hidden group cursor-pointer"
                @click="openPhoto(photo)"
            >
                <!-- Photo image -->
                <div class="aspect-square bg-slate-100 relative">
                    <img
                        v-if="photo.filename"
                        :src="resolvePhotoUrl(photo.filename)"
                        :alt="`Photo ${photo.id}`"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                    <div v-else class="flex items-center justify-center h-full text-slate-300">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <!-- Status badges -->
                    <div class="absolute top-2 right-2 flex gap-1">
                        <span
                            class="px-1.5 py-0.5 text-xs font-medium rounded"
                            :class="verificationClass(photo.verified)"
                        >
                            {{ verificationLabel(photo.verified) }}
                        </span>
                        <span
                            v-if="!photo.is_public"
                            class="px-1.5 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-700"
                        >
                            Private
                        </span>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500">{{ photo.photo_tags?.length || 0 }} tags</span>
                        <span class="text-xs text-slate-500">{{ formatDate(photo.created_at) }}</span>
                    </div>
                    <p v-if="photo.user" class="text-xs text-slate-500 mt-1 truncate">
                        by {{ photo.user.name }}
                    </p>
                    <button
                        v-if="isLeader && photo.is_public && photo.team_approved_at"
                        class="mt-2 w-full px-2 py-1 text-xs font-medium rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                        @click.stop="revokePhoto(photo.id)"
                    >
                        Revoke
                    </button>
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

        <!-- Photo detail modal -->
        <TeamPhotoEdit
            v-if="selectedPhoto"
            :photo="selectedPhoto"
            :team-id="teamId"
            :is-leader="isLeader"
            :is-school-team="isSchoolTeam"
            @close="selectedPhoto = null"
            @saved="onPhotoSaved"
            @deleted="onPhotoDeleted"
        />
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useTeamPhotosStore } from '@/stores/teamPhotos';
import { resolvePhotoUrl } from '@/composables/usePhotoUrl';
import TeamPhotoEdit from './TeamPhotoEdit.vue';

const VERIFICATION_LABELS = {
    0: 'Unverified',
    1: 'Verified',
    2: 'Approved',
    3: 'BBox',
    4: 'BBox Verified',
    5: 'AI Ready',
};

const VERIFICATION_CLASSES = {
    0: 'bg-slate-100 text-slate-600',
    1: 'bg-blue-100 text-blue-700',
    2: 'bg-green-100 text-green-700',
    3: 'bg-amber-100 text-amber-700',
    4: 'bg-purple-100 text-purple-700',
    5: 'bg-emerald-100 text-emerald-700',
};

export default {
    name: 'TeamPhotoList',
    components: { TeamPhotoEdit },
    props: {
        teamId: { type: Number, required: true },
        isLeader: { type: Boolean, default: false },
        isSchoolTeam: { type: Boolean, default: false },
    },
    setup(props) {
        const store = useTeamPhotosStore();
        const selectedPhoto = ref(null);

        const photos = computed(() => store.photos);
        const loading = computed(() => store.loading);
        const filter = computed(() => store.filter);

        const filters = [
            { value: 'all', label: 'All' },
            { value: 'pending', label: 'Pending' },
            { value: 'approved', label: 'Approved' },
        ];

        const setFilter = (value) => {
            store.setFilter(value);
            store.fetchPhotos(props.teamId);
        };

        const changePage = (page) => store.fetchPhotos(props.teamId, page);

        const openPhoto = (photo) => {
            selectedPhoto.value = photo;
        };

        const onPhotoSaved = () => {
            selectedPhoto.value = null;
            store.fetchPhotos(props.teamId, photos.value.current_page);
        };

        const onPhotoDeleted = () => {
            selectedPhoto.value = null;
        };

        const revokePhoto = async (photoId) => {
            if (!confirm('Revoke approval? This photo will become private and metrics will be reversed.')) return;
            await store.revokePhotos(props.teamId, [photoId]);
        };

        const formatDate = (date) => {
            return new Intl.DateTimeFormat('en-IE', {
                month: 'short',
                day: 'numeric',
            }).format(new Date(date));
        };

        const verificationLabel = (v) => VERIFICATION_LABELS[v] ?? 'Unknown';
        const verificationClass = (v) => VERIFICATION_CLASSES[v] ?? VERIFICATION_CLASSES[0];

        return {
            photos, loading, filter, filters, selectedPhoto,
            setFilter, changePage, openPhoto, onPhotoSaved, onPhotoDeleted, revokePhoto,
            formatDate, verificationLabel, verificationClass, resolvePhotoUrl,
        };
    },
};
</script>
