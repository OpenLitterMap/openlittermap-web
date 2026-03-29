<template>
    <div
        class="relative bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900 overflow-y-auto"
        style="min-height: calc(100vh - 73px)"
    >
        <!-- Ambient orbs -->
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal-500/[0.07] blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/3 -right-32 w-[400px] h-[400px] rounded-full bg-blue-500/[0.08] blur-3xl pointer-events-none"></div>

        <!-- Onboarding step indicator -->
        <div v-if="onboarding" class="relative z-10 max-w-3xl mx-auto px-4 md:px-8 pt-4">
            <StepIndicator :current-step="2" />
        </div>

        <!-- Header -->
        <div class="relative z-10 flex items-center justify-between px-4 md:px-8 py-3">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-white">{{ $t('Upload') }}</h1>
                <p class="text-sm text-white/40 mt-0.5">
                    <template v-if="activeTeam">
                        {{ $t('Uploads go to') }} <span class="text-white/60 font-medium">{{ activeTeam.name }}</span>
                        <span
                            v-if="activeTeam.type_name"
                            class="ml-1.5 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/10 text-white/40"
                        >{{ activeTeam.type_name }}</span>
                    </template>
                    <template v-else>{{ $t('Uploads go to your personal collection') }}</template>
                </p>
            </div>
            <div class="flex items-center gap-4">
                <div v-if="sessionXp > 0" class="flex items-center gap-1.5 bg-emerald-500/15 border border-emerald-500/20 rounded-lg px-3 py-1.5">
                    <span class="text-emerald-400 text-sm font-bold tabular-nums">+{{ sessionXp }} XP</span>
                </div>
                <div v-if="userLevel" class="hidden md:flex items-center gap-2">
                    <div class="bg-gradient-to-r from-yellow-500 to-amber-500 w-7 h-7 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-xs">{{ userLevel.level }}</span>
                    </div>
                    <div>
                        <div class="text-white/60 text-xs font-medium">{{ userLevel.title }}</div>
                        <div class="w-20 h-1 bg-white/10 rounded-full mt-0.5">
                            <div
                                class="h-full bg-amber-400/80 rounded-full transition-all duration-500"
                                :style="{ width: (userLevel.progress_percent || 0) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Centered upload area -->
        <div class="relative z-10 max-w-3xl mx-auto px-4 md:px-8 pb-6">
            <div class="border-2 border-dashed border-white/20 hover:border-emerald-400/40 rounded-2xl transition-colors duration-300 p-4 md:p-6">
                <FilePond
                    ref="pond"
                    name="photo"
                    allowMultiple
                    :maxFiles="null"
                    max-file-size="20MB"
                    :maxParallelUploads="3"
                    :labelIdle="pondLabel"
                    class="custom-filepond"
                    :server="server"
                    :acceptedFileTypes="acceptedFileTypes"
                    :dropOnPage="true"
                    :dropOnElement="false"
                    @processfile="handleFileProcessed"
                    :labelFileProcessingError="errorLabelFn"
                />
            </div>
            <div class="flex flex-wrap justify-center gap-2 mt-3 text-white/30 text-xs">
                <span class="bg-white/5 rounded-full px-2.5 py-1">{{ $t('Close-up, object fills frame') }}</span>
                <span class="bg-white/5 rounded-full px-2.5 py-1">{{ $t('No people, no personal info') }}</span>
                <span class="bg-white/5 rounded-full px-2.5 py-1">{{ $t('Tag after upload for full XP') }}</span>
            </div>
            <p class="text-center text-white/20 text-xs mt-1.5">
                JPEG, PNG, WebP, HEIC &middot; 20 MB max
            </p>

            <!-- Tag CTA (appears after uploads complete) -->
            <div v-if="successCount > 0 && allDone" class="mt-6 text-center">
                <router-link
                    :to="onboarding ? '/onboarding/tag' : '/tag'"
                    class="inline-flex items-center justify-center gap-2 w-full sm:w-auto bg-emerald-500 hover:bg-emerald-400 px-8 py-3.5 rounded-xl text-white font-semibold transition-all duration-200 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-400/30"
                >
                    {{ $t('Tag your photos') }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </router-link>
            </div>

            <!-- Failure recovery (all files failed) -->
            <div v-if="allDone && successCount === 0" class="mt-6 text-center">
                <p class="text-red-400/80 text-sm mb-3">{{ $t('Upload failed. Please try again.') }}</p>
                <button
                    @click="resetUpload"
                    class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 px-6 py-3 rounded-xl text-white font-medium transition-all"
                >
                    {{ $t('Try again') }}
                </button>
            </div>

            <!-- GPS error help -->
            <div v-if="hasGpsError" class="mt-4 max-w-lg mx-auto">
                <p class="mb-3 text-sm text-red-400/80">
                    This photo doesn't have location data. Make sure GPS is enabled on your camera:
                </p>
                <GpsInstructions compact />
            </div>
        </div>
    </div>
</template>

<script setup>
import vueFilePond from 'vue-filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginImageResize from 'filepond-plugin-image-resize';
import FilePondPluginImageExifOrientation from 'filepond-plugin-image-exif-orientation';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css';

import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useUploadingStore } from '../../stores/uploading/index.js';
import { useUserStore } from '../../stores/user/index.js';
import StepIndicator from '@/components/onboarding/StepIndicator.vue';
import GpsInstructions from '@/components/onboarding/GpsInstructions.vue';
import { useTagsStore } from '@stores/tags/index.js';
import { usePhotosStore } from '@stores/photos/index.js';

const props = defineProps({
    onboarding: {
        type: Boolean,
        default: false,
    },
});

// Preload tags when in onboarding (user will need them on the next page)
if (props.onboarding) {
    const tagsStore = useTagsStore();
    if (tagsStore.objects.length === 0) {
        tagsStore.GET_ALL_TAGS();
    }
}

const { t } = useI18n();
const router = useRouter();
const uploadingStore = useUploadingStore();
const userStore = useUserStore();

const FilePond = vueFilePond(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateType,
    FilePondPluginImageResize,
    FilePondPluginImageExifOrientation,
    FilePondPluginFileValidateSize,
);

// ── State ────────────────────────────────────────────────────────
const pond = ref(null);
const sessionXp = ref(0);
const successCount = ref(0);
const allDone = ref(false);
const hasGpsError = ref(false);

const activeTeam = computed(() => userStore.user?.team || null);
const userLevel = computed(() => userStore.user?.next_level || null);

// ── FilePond Config ──────────────────────────────────────────────
const acceptedFileTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', '.heic', '.heif'];

const pondLabel = computed(
    () =>
        `<div class="flex flex-col items-center gap-3 py-4">` +
        `<svg class="w-14 h-14 md:w-16 md:h-16 text-emerald-400/50 animate-float" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" /></svg>` +
        `<div class="text-xl md:text-2xl font-semibold text-white">${t('Drop your photos here')}</div>` +
        `<div class="text-white/40 text-sm">${t('or')} <span class="text-emerald-400 cursor-pointer">${t('browse to select files')}</span></div>` +
        `</div>`,
);

function getXsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const server = {
    url: '.',
    process: (fieldName, file, metadata, load, error, progress, abort) => {
        const formData = new FormData();
        formData.append(fieldName, file, file.name);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/v3/upload');
        xhr.withCredentials = true;
        xhr.timeout = 120000;

        // Read XSRF-TOKEN cookie at request time so it's always fresh
        xhr.setRequestHeader('X-XSRF-TOKEN', getXsrfToken());
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = (e) => {
            progress(e.lengthComputable, e.loaded, e.total);
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                load(xhr.responseText);
            } else {
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    if (parsed.error === 'no_gps' || parsed.error === 'invalid_coordinates') {
                        hasGpsError.value = true;
                    }
                    error(parsed.message || parsed.error || 'Upload failed');
                } catch {
                    error('Upload failed');
                }
            }
        };

        xhr.onerror = () => error('Upload failed');
        xhr.ontimeout = () => error('Upload timed out');

        xhr.send(formData);

        return {
            abort: () => {
                xhr.abort();
                abort();
            },
        };
    },
};

const errorLabelFn = (error) => error.body;

function resetUpload() {
    allDone.value = false;
    successCount.value = 0;
    sessionXp.value = 0;
    hasGpsError.value = false;
    if (pond.value) {
        pond.value.removeFiles();
    }
}

// ── Single event handler: file finished processing ──────────────
const handleFileProcessed = (error, file) => {
    if (!error) {
        try {
            const data = JSON.parse(file.serverId);
            sessionXp.value += data.xp_awarded || 5;
        } catch {
            sessionXp.value += 5;
        }
        successCount.value++;
    }

    // Check if all files done
    if (pond.value) {
        const files = pond.value.getFiles();
        const done = files.every((f) => f.status === 5 || f.status === 6);
        if (done) {
            allDone.value = true;
            uploadingStore.setIsUploading(false);
            userStore.REFRESH_USER();

            // Auto-redirect in onboarding mode
            if (props.onboarding && successCount.value > 0) {
                // Prime the photos cache so the tag page loads instantly
                const photosStore = usePhotosStore();
                photosStore.fetchUntaggedData(1, { tagged: false });

                router.push('/onboarding/tag');
            }
        }
    }
};
</script>

<style>
/* Float animation for upload icon */
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.animate-float {
    animation: float 3s ease-in-out infinite;
}

/* ── FilePond dark theme ── */
.custom-filepond .filepond--panel-root {
    background-color: transparent;
}
.custom-filepond .filepond--drop-label {
    color: rgba(255, 255, 255, 0.5);
}
.custom-filepond .filepond--drop-label label {
    cursor: pointer;
}
.custom-filepond .filepond--label-action {
    text-decoration: none;
}
.custom-filepond .filepond--credits {
    display: none !important;
}

/* Tall drop label for large drop target */
.custom-filepond .filepond--drop-label {
    min-height: 300px;
}
@media (max-width: 768px) {
    .custom-filepond .filepond--drop-label {
        min-height: 200px;
    }
}

/* Full-page drop overlay */
.filepond--panel-root[data-scalable="true"] {
    background-color: transparent;
}

/* File item panel backgrounds */
.custom-filepond .filepond--item-panel {
    background-color: rgba(255, 255, 255, 0.1);
}
.custom-filepond [data-filepond-item-state="processing-complete"] .filepond--item-panel {
    background-color: rgba(16, 185, 129, 0.2);
}
.custom-filepond [data-filepond-item-state="processing-error"] .filepond--item-panel,
.custom-filepond [data-filepond-item-state="load-invalid"] .filepond--item-panel {
    background-color: rgba(239, 68, 68, 0.2);
}

/* File info text */
.custom-filepond .filepond--file-info {
    color: rgba(255, 255, 255, 0.8);
}
.custom-filepond .filepond--file-info .filepond--file-info-sub {
    color: rgba(255, 255, 255, 0.4);
    opacity: 1;
}

/* File status text */
.custom-filepond .filepond--file-status {
    color: rgba(255, 255, 255, 0.8);
}
.custom-filepond .filepond--file-status .filepond--file-status-sub {
    color: rgba(255, 255, 255, 0.4);
    opacity: 1;
}

/* Action buttons */
.custom-filepond .filepond--file-action-button {
    cursor: pointer;
    color: rgba(255, 255, 255, 0.6);
    background-color: rgba(255, 255, 255, 0.1);
}
.custom-filepond .filepond--file-action-button:hover {
    color: white;
    background-color: rgba(255, 255, 255, 0.2);
}

/* Processing indicator */
.custom-filepond .filepond--processing-complete-indicator {
    color: #34d399;
}
.custom-filepond .filepond--load-indicator {
    color: rgba(255, 255, 255, 0.6);
}

/* Progress indicator */
.custom-filepond .filepond--progress-indicator {
    color: rgba(255, 255, 255, 0.6);
}
.custom-filepond .filepond--progress-indicator svg {
    color: rgba(255, 255, 255, 0.6);
}
</style>
