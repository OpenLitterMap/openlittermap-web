<template>
    <div
        class="flex justify-center px-20 pt-20 bg-gradient-to-r from-amber-200 to-yellow-500"
    >
        <div>
            <h1 class="text-5xl font-semibold mb-8 text-center">Click or Drop to upload your photos</h1>

            <div class="text-center mb-6">

                <!-- Display after uploading one file successfully -->
                <p class="text-2xl font-bold-500 mb-4">Next you need to tag the litter</p>

                <button class="bg-[#2793da] px-10 py-4 rounded-2xl text-white hov">
                    Tag Litter &nbsp;

                    <i data-v-fcf00e23="" aria-hidden="true" class="fa fa-arrow-right"></i>
                </button>
            </div>

            <FilePond
                name="photo"
                allowMultiple
                max-file-size="20MB"
                labelIdle='Drag & Drop your files or <span class="text-blue-500">Browse</span>'
                class="custom-filepond"
                :server="server"
                :acceptedFileTypes="acceptedFileTypes"
                @processfile="handleFileUpload"
                style="height: 10em !important;"
                :labelFileProcessingError="options.labelFileProcessingError"
            />

            <div class="text-center">
<!--                <p class="text-2xl mb-8">Team: todo</p>-->

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

import { useToast } from "vue-toastification";
const toast = useToast();

const FilePond = vueFilePond(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateType,
    FilePondPluginImageResize,
    FilePondPluginImageExifOrientation,
    FilePondPluginFileValidateSize,
);

const acceptedFileTypes = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/webp',
    '.heic',
    '.heif',
];

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
        onerror: (response, file, load, error, progress, abort) => {
            try {
                const errorResponse = JSON.parse(response);

                return errorResponse.error || 'An unknown error occurred.';
            } catch (e) {
                console.error('Error parsing response:', e);

                return 'An unknown error occurred. Please contact support';
            }
        },
        ondata: null,
    },
    fetch: null,
    revert: null
};

// Configure FilePond labels
const options = {
    // FilePond label to display when an upload fails
    labelFileProcessingError: (error) => {
        return error.body;
    },
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
    }
};

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

</style>
