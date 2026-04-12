<template>
    <div>
        <!-- Filter bar -->
        <TeamPhotosHeader
            :members="memberStats"
            :exporting="exporting"
            @apply="onApplyFilters"
            @export="onExport"
        />

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12 text-white/40">Loading photos...</div>

        <!-- Empty state -->
        <div v-else-if="photos.data.length === 0" class="text-center py-12">
            <p class="text-white/40">No photos found.</p>
            <p class="text-sm text-white/30 mt-1">Upload photos with this team set as active to see them here.</p>
        </div>

        <!-- Photo grid -->
        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div
                v-for="photo in photos.data"
                :key="photo.id"
                class="bg-white/5 border border-white/10 rounded-xl overflow-hidden group cursor-pointer hover:bg-white/10 transition-colors"
                @click="openPhoto(photo)"
            >
                <!-- Photo image -->
                <div class="aspect-square bg-white/5 relative">
                    <img
                        v-if="photo.filename"
                        :src="resolvePhotoUrl(photo.filename)"
                        :alt="`Photo ${photo.id}`"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                    <div v-else class="flex items-center justify-center h-full text-white/20">
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
                            class="px-1.5 py-0.5 text-xs font-medium rounded bg-amber-500/20 text-amber-400"
                        >
                            Private
                        </span>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-white/60">{{ photo.photo_tags?.length || 0 }} tags</span>
                        <span class="text-xs text-white/40">{{ formatDate(photo.created_at) }}</span>
                    </div>
                    <p v-if="photo.user" class="text-xs text-white/40 mt-1 truncate">
                        by {{ photo.user.name }}
                    </p>
                    <button
                        v-if="isLeader && photo.is_public && photo.team_approved_at"
                        class="mt-2 w-full px-2 py-1 text-xs font-medium rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500/20 transition-colors"
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
                class="px-4 py-2 text-sm rounded-lg border border-white/20 bg-white/5 text-white disabled:opacity-40 hover:bg-white/10 transition-colors"
                @click="changePage(photos.current_page - 1)"
            >
                Previous
            </button>
            <span class="px-3 py-2 text-sm text-white/40">
                {{ photos.current_page }} / {{ photos.last_page }}
            </span>
            <button
                :disabled="photos.current_page >= photos.last_page"
                class="px-4 py-2 text-sm rounded-lg border border-white/20 bg-white/5 text-white disabled:opacity-40 hover:bg-white/10 transition-colors"
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

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useTeamPhotosStore } from '@/stores/teamPhotos';
import { useTeamsStore } from '@/stores/teams';
import { resolvePhotoUrl } from '@/composables/usePhotoUrl';
import TeamPhotoEdit from './TeamPhotoEdit.vue';
import TeamPhotosHeader from './components/TeamPhotosHeader.vue';

const VERIFICATION_LABELS = {
    0: 'Unverified',
    1: 'Verified',
    2: 'Approved',
    3: 'BBox',
    4: 'BBox Verified',
    5: 'AI Ready',
};

const VERIFICATION_CLASSES = {
    0: 'bg-slate-500/20 text-slate-300',
    1: 'bg-blue-500/20 text-blue-300',
    2: 'bg-green-500/20 text-green-300',
    3: 'bg-amber-500/20 text-amber-300',
    4: 'bg-purple-500/20 text-purple-300',
    5: 'bg-emerald-500/20 text-emerald-300',
};

const props = defineProps({
    teamId: { type: Number, required: true },
    isLeader: { type: Boolean, default: false },
    isSchoolTeam: { type: Boolean, default: false },
});

const toast = useToast();
const store = useTeamPhotosStore();
const teamsStore = useTeamsStore();
const selectedPhoto = ref(null);
const exporting = ref(false);
const currentFilters = ref({});

const photos = computed(() => store.photos);
const loading = computed(() => store.loading);
const memberStats = computed(() => store.memberStats);

const onApplyFilters = (filters) => {
    currentFilters.value = filters;

    // Extract status for the store's filter state
    if (filters.status) {
        store.setFilter(filters.status);
    }

    store.fetchPhotos(props.teamId, 1, filters);
};

const onExport = async (filters) => {
    exporting.value = true;
    try {
        await teamsStore.downloadTeamData(props.teamId, filters);
        toast.success('Export started — check your email for the download link.');
    } catch {
        toast.error('Export failed. Please try again.');
    } finally {
        exporting.value = false;
    }
};

const changePage = (page) => store.fetchPhotos(props.teamId, page, currentFilters.value);

const openPhoto = (photo) => {
    selectedPhoto.value = photo;
};

const onPhotoSaved = () => {
    selectedPhoto.value = null;
    store.fetchPhotos(props.teamId, photos.value.current_page, currentFilters.value);
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

onMounted(() => {
    // Load member stats for the member filter dropdown
    if (store.memberStats.length === 0) {
        store.fetchMemberStats(props.teamId);
    }
});
</script>
