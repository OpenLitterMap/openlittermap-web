<template>
    <div
        class="flex relative justify-center px-20 pt-20 bg-gradient-to-r from-amber-200 to-yellow-500"
        style="min-height: calc(100% - 72px)"
    >
        <div class="h-full">
            <h1 class="text-5xl font-semibold mb-10 text-center">Click or Drop to upload your photos</h1>

            <div v-if="uploadProgress > 0" class="text-center mb-6">
                <p>Upload Progress: {{ uploadProgress.toFixed() }}%</p>
            </div>

            <p v-if="team" class="text-center">
                {{ $t('common.team') }}: <strong>{{ team }}</strong>
            </p>

            <!-- After uploading-->
            <transition name="fade">
                <div v-if="uploadSuccess" class="text-center mb-6">
                    <p class="text-2xl font-bold-500 mb-4">Next you need to tag the litter</p>

                    <router-link to="/tag" class="bg-[#2793da] px-6 py-4 rounded-2xl text-white hov">
                        Tag Litter &nbsp;

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
                <p class="text-4xl font-bold">Thank you!</p>
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
    url: '.', // current host
    process: {
        url: '/upload',
        method: 'POST',
        withCredentials: false,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        timeout: 120000, // 2 minutes
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

// Configure FilePond labels
const options = {
    // FilePond label to display when an upload fails
    labelFileProcessingError: (error) => {
        return error.body;
    },
};

import { useUploadingStore } from '../../stores/uploading/index.js';
const uploadingStore = useUploadingStore();
const isUploading = computed(() => uploadingStore.isUploading);

// Handle when a file is dropped onto the view
const handleFileAdded = () => {
    uploadingStore.setIsUploading(true);
};

// Handle file upload success and errors
const handleFileUpload = (error, file) => {
    if (error) {
        console.error('Error uploading file:', error);
    } else {
        console.log('File uploaded successfully:', file);

        const name = file.file.name;

        // Show success notification
        toast.success(`File ${name} uploaded successfully`);

        updateProgress();

        uploadSuccess.value = true;
    }
};

const updateProgress = () => {
    const files = pond.value.getFiles();

    // https://pqina.nl/filepond/docs/api/exports/#filestatus
    const processedFiles = files.filter((file) => file.status === 5); // PROCESSING_COMPLETE

    uploadProgress.value = files.length ? (processedFiles.length / files.length) * 100 : 0;
};

const team = computed(() => {
    return userStore.user?.team?.name;
});
</script>

<style>
@media (max-width: 1300px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 50em; /* Shrink width for medium screens */
    }
}

@media (max-width: 1024px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 40em; /* Shrink width for medium screens */
    }
}

@media (max-width: 768px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 30em; /* Shrink width for small screens */
    }
}

@media (max-width: 520px) {
    .filepond--root,
    .filepond--root .filepond--drop-label {
        width: 20em; /* Shrink width for very small screens */
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
