<template>
    <div :style="isUploading ? '' : 'height: 100%'">
        <Nav />

        <Modal />

        <router-view
            style="height: calc(100% - 90px);"
        />
    </div>
</template>

<script setup>

import { useUploadingStore } from "./stores/uploading/index.js";
import {computed} from "vue";

// There is a bug when displaying the height on the upload page.
const uploadingStore = useUploadingStore();
uploadingStore.setIsUploading(false);
const isUploading = computed(() => uploadingStore.isUploading);

</script>
