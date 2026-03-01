<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 px-4 py-3">
            <div class="max-w-4xl mx-auto flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-slate-800">{{ session?.team_name }}</h1>
                    <p class="text-sm text-slate-500">{{ session?.display_name }} (Slot {{ session?.slot_number }})</p>
                </div>
                <button
                    class="text-sm text-red-500 hover:text-red-600 font-medium"
                    @click="logout"
                >
                    Leave Session
                </button>
            </div>
        </div>

        <!-- Tab navigation -->
        <div class="bg-white border-b border-slate-200">
            <div class="max-w-4xl mx-auto flex">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    class="px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors"
                    :class="activeTab === tab.id
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-slate-500 hover:text-slate-700'"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.badge"
                        class="ml-1.5 inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700"
                    >
                        {{ tab.badge }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 py-6">
            <!-- Upload Tab -->
            <div v-if="activeTab === 'upload'">
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4">Upload a Photo</h2>

                    <div
                        class="border-2 border-dashed border-slate-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors cursor-pointer"
                        @click="fileInput?.click()"
                        @dragover.prevent
                        @drop.prevent="handleDrop"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                        <p v-if="!uploadPreview" class="text-slate-400">
                            Click to take a photo or select from gallery
                        </p>
                        <img
                            v-else
                            :src="uploadPreview"
                            class="max-h-64 mx-auto rounded-lg"
                            alt="Upload preview"
                        />
                    </div>

                    <button
                        v-if="uploadFile"
                        :disabled="uploading"
                        class="mt-4 w-full py-2 rounded-lg text-white font-medium text-sm transition-colors"
                        :class="uploading ? 'bg-slate-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700'"
                        @click="uploadPhoto"
                    >
                        {{ uploading ? 'Uploading...' : 'Upload Photo' }}
                    </button>

                    <p v-if="uploadError" class="text-red-500 text-sm mt-2">{{ uploadError }}</p>
                    <p v-if="uploadSuccess" class="text-green-600 text-sm mt-2">Photo uploaded! Go to My Photos to tag it.</p>
                </div>
            </div>

            <!-- My Photos Tab -->
            <div v-if="activeTab === 'photos'">
                <div v-if="photos.length === 0 && !loadingPhotos" class="text-center py-12 text-slate-400">
                    No photos yet. Upload your first photo!
                </div>

                <div v-else-if="loadingPhotos" class="text-center py-12 text-slate-400">Loading...</div>

                <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div
                        v-for="photo in photos"
                        :key="photo.id"
                        class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm"
                    >
                        <div class="aspect-square bg-slate-100 relative">
                            <img
                                :src="`/storage/${photo.filename}`"
                                class="w-full h-full object-cover"
                                alt="Uploaded photo"
                            />
                            <span
                                class="absolute top-2 right-2 text-xs px-2 py-0.5 rounded-full"
                                :class="photo.total_tags > 0 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ photo.total_tags > 0 ? `${photo.total_tags} tags` : 'Untagged' }}
                            </span>
                        </div>
                        <div class="p-3 space-y-2">
                            <p class="text-xs text-slate-500">{{ formatDate(photo.created_at) }}</p>
                            <div class="flex gap-2">
                                <button
                                    v-if="photo.total_tags === 0"
                                    class="text-xs px-2 py-1 text-blue-600 border border-blue-200 rounded hover:bg-blue-50 flex-1"
                                    @click="tagPhoto(photo.id)"
                                >
                                    Tag
                                </button>
                                <button
                                    v-if="!photo.team_approved_at"
                                    class="text-xs px-2 py-1 text-red-600 border border-red-200 rounded hover:bg-red-50"
                                    @click="deletePhoto(photo.id)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tag Tab -->
            <div v-if="activeTab === 'tag'">
                <div v-if="!selectedPhotoId" class="text-center py-12 text-slate-400">
                    Select a photo from "My Photos" to start tagging.
                </div>
                <div v-else class="bg-white rounded-xl p-6 shadow-sm">
                    <p class="text-sm text-slate-500 mb-4">Tagging photo #{{ selectedPhotoId }}</p>
                    <p class="text-slate-400 text-sm">
                        Use the full tagging interface at
                        <router-link :to="`/tag?photo=${selectedPhotoId}`" class="text-blue-600 hover:underline">
                            /tag?photo={{ selectedPhotoId }}
                        </router-link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const router = useRouter();
const toast = useToast();

const session = ref(null);
const activeTab = ref('upload');
const photos = ref([]);
const loadingPhotos = ref(false);
const uploadFile = ref(null);
const uploadPreview = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const uploadSuccess = ref(false);
const selectedPhotoId = ref(null);
const fileInput = ref(null);

const tabs = computed(() => [
    { id: 'upload', label: 'Upload', badge: null },
    { id: 'photos', label: 'My Photos', badge: photos.value.length || null },
    { id: 'tag', label: 'Tag', badge: null },
]);

// Axios interceptor for participant token
let interceptorId = null;

const setupInterceptor = () => {
    const token = localStorage.getItem('participant_token');
    if (!token) return;

    interceptorId = axios.interceptors.request.use((config) => {
        // Only add token to participant API routes
        if (config.url?.startsWith('/api/participant/')) {
            config.headers['X-Participant-Token'] = token;
        }
        return config;
    });
};

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

const fetchPhotos = async () => {
    loadingPhotos.value = true;
    try {
        const { data } = await axios.get('/api/participant/photos');
        photos.value = data.photos?.data || [];
    } catch (e) {
        // Token might be invalid
        if (e.response?.status === 401) {
            logout();
        }
    } finally {
        loadingPhotos.value = false;
    }
};

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) setFile(file);
};

const handleDrop = (e) => {
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) setFile(file);
};

const setFile = (file) => {
    if (uploadPreview.value) URL.revokeObjectURL(uploadPreview.value);
    uploadFile.value = file;
    uploadPreview.value = URL.createObjectURL(file);
    uploadSuccess.value = false;
    uploadError.value = '';
};

const uploadPhoto = async () => {
    if (!uploadFile.value) return;
    uploading.value = true;
    uploadError.value = '';
    uploadSuccess.value = false;

    try {
        const formData = new FormData();
        formData.append('photo', uploadFile.value);

        await axios.post('/api/participant/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        uploadSuccess.value = true;
        uploadFile.value = null;
        if (uploadPreview.value) URL.revokeObjectURL(uploadPreview.value);
        uploadPreview.value = null;
        toast.success('Photo uploaded!');
        fetchPhotos();
    } catch (e) {
        uploadError.value = e.response?.data?.message || 'Upload failed.';
    } finally {
        uploading.value = false;
    }
};

const deletePhoto = async (id) => {
    if (!confirm('Delete this photo?')) return;

    try {
        await axios.delete(`/api/participant/photos/${id}`);
        photos.value = photos.value.filter((p) => p.id !== id);
        toast.success('Photo deleted.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to delete.');
    }
};

const tagPhoto = (id) => {
    selectedPhotoId.value = id;
    activeTab.value = 'tag';
};

const logout = () => {
    localStorage.removeItem('participant_token');
    localStorage.removeItem('participant_session');
    router.push({ name: 'ParticipantEntry' });
};

onMounted(() => {
    const sessionData = localStorage.getItem('participant_session');
    if (!sessionData) {
        router.push({ name: 'ParticipantEntry' });
        return;
    }

    session.value = JSON.parse(sessionData);
    setupInterceptor();
    fetchPhotos();
});

onUnmounted(() => {
    if (interceptorId !== null) {
        axios.interceptors.request.eject(interceptorId);
    }
});
</script>
