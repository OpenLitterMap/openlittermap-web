<template>
    <div
        class="flex relative justify-center px-20 pt-20 bg-gradient-to-r from-amber-200 to-yellow-500"
        style="min-height: calc(100% - 72px)"
    >
        <div class="h-full">
            <h1 class="text-5xl font-semibold mb-10 text-center">{{ $t('Click or Drop to upload your photos') }}</h1>

            <div v-if="uploadProgress > 0" class="text-center mb-6">
                <p>{{ $t('Upload Progress') }}: {{ uploadProgress.toFixed() }}%</p>
            </div>

            <!-- After uploading-->
            <transition name="fade">
                <div v-if="uploadSuccess" class="text-center mb-6">
                    <p class="text-2xl font-bold-500 mb-4">{{ $t('Next you need to tag the litter') }}</p>

                    <router-link to="/tag" class="bg-[#2793da] px-6 py-4 rounded-2xl text-white hov">
                        {{ $t('Tag Litter') }} &nbsp;

                        <i data-v-fcf00e23="" aria-hidden="true" class="fa fa-arrow-right"></i>
                    </router-link>
                </div>
            </transition>

            <FilePond
                ref="pond"
                name="photo"
                allowMultiple
                max-file-size="20MB"
                labelIdle='Drag & Drop your files or <span class="text-blue-500">Browse</span>'
                class="custom-filepond"
                :server="server"
                :acceptedFileTypes="acceptedFileTypes"
                @addfile="handleFileAdded"
                @processfile="handleFileUpload"
                :labelFileProcessingError="options.labelFileProcessingError"
            />

            <div class="text-center mt-10 pb-10">
                <p class="text-4xl font-bold">{{ $t('Thank you!') }}</p>
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

import { useToast } from 'vue-toastification';
const toast = useToast();

import { computed, ref } from 'vue';
const uploadSuccess = ref(false);

import { useUserStore } from '../../stores/user/index.js';
const userStore = useUserStore();

const FilePond = vueFilePond(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateType,
    FilePondPluginImageResize,
    FilePondPluginImageExifOrientation,
    FilePondPluginFileValidateSize
);

const uploadProgress = ref(0);
const pond = ref(null);

const acceptedFileTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', '.heic', '.heif'];

const server = {
    url: '.',
    process: {
        url: '/api/upload',
        method: 'POST',
        withCredentials: true,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        timeout: 120000,
        onload: null,
        onerror: (response) => {
            try {
                const errorResponse = JSON.parse(response);
                return errorResponse.error || 'An unknown error occurred.';
            } catch (e) {
                console.error('Error parsing response:', e);
                return 'An unknown error occurred. Please contact support';
            }
        },
    },
};

const options = {
    labelFileProcessingError: (error) => {
        return error.body;
    },
};

import { useUploadingStore } from '../../stores/uploading/index.js';
const uploadingStore = useUploadingStore();
const isUploading = computed(() => uploadingStore.isUploading);

const handleFileAdded = () => {
    uploadingStore.setIsUploading(true);
};

const handleFileUpload = (error, file) => {
    if (error) {
        console.error('Error uploading file:', error);
    } else {
        const name = file.file.name;

        toast.success(`File ${name} uploaded successfully`);

        updateProgress();

        uploadSuccess.value = true;
    }

    // Reset isUploading when all files are done (processed or errored)
    const files = pond.value?.getFiles() || [];
    const pending = files.filter((f) => f.status !== 5 && f.status !== 6 && f.status !== 8);
    if (pending.length === 0) {
        uploadingStore.setIsUploading(false);
    }
};

const updateProgress = () => {
    const files = pond.value.getFiles();

    // https://pqina.nl/filepond/docs/api/exports/#filestatus
    const processedFiles = files.filter((file) => file.status === 5); // PROCESSING_COMPLETE

    uploadProgress.value = files.length ? (processedFiles.length / files.length) * 100 : 0;
};

</script>

<style>
@media (max-width: 1300px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 50em;
    }
}

@media (max-width: 1024px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 40em;
    }
}

@media (max-width: 768px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 30em;
    }
}

@media (max-width: 520px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 20em;
    }
}

.fade-enter-active {
    transition: opacity 2s ease;
}
.fade-enter-from {
    opacity: 0;
    transform: translateX(50px);
}
.fade-enter-to {
    opacity: 1;
    transform: translateX(0);
}
</style>
