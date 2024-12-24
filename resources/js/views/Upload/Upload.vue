<template>
    <div
        class="flex items-center justify-center p-20 bg-gradient-to-r from-amber-200 to-yellow-500"
    >
        <div>
            <h1 class="text-5xl font-semibold mb-8 text-center">Click or Drop to upload your photos</h1>

            <div>
                <p>Team: todo</p>
            </div>

            <FilePond
                name="photos"
                allowMultiple
                labelIdle='Drag & Drop your files or <span class="text-blue-500">Browse</span>'
                class="custom-filepond"
                :server="server"
                :acceptedFileTypes="acceptedFileTypes"
                @processfile="handleFileUpload"
                style="height: 10em !important;"
            />
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
            'X-CSRF-TOKEN': window.axios.defaults.headers.common['X-CSRF-TOKEN'],
        },
        timeout: 120000, // 2 minutes
        onload: null,
        onerror: null,
        ondata: null,
    },
    fetch: null,
    revert: null,
};

// Handle file upload success and errors
const handleFileUpload = (error, file) => {
    if (error) {
        console.error('Error uploading file:', error);
    } else {
        console.log('File uploaded successfully:', file);
    }
};
</script>

<style>

    .filepond--root,
    .filepond--root .filepond--drop-label {
        height: 10em;
        width: 75em;
    }

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
