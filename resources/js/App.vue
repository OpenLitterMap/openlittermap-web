<template>
    <div class="h-full">
        <Nav />

        <Modal />

        <!-- style="height: calc(100% - 90px);"-->
        <router-view />
    </div>
</template>

<script setup>
import { useUserStore } from "./stores/user/index.js";
const userStore = useUserStore();
userStore.CHECK_AUTH();

import { useUploadingStore } from "./stores/uploading/index.js";
import { computed, watch, ref } from "vue";
import { useRoute } from "vue-router";

// There is a bug when displaying the height on the upload page.
const uploadingStore = useUploadingStore();
uploadingStore.setIsUploading(false);
const isUploading = computed(() => uploadingStore.isUploading);

const route = useRoute();
const routeName = computed(() => route.name);

const showFullHeight = ref(true);

// watch([isUploading, () => route.name], ([isUploadingValue, routeName]) => {
//     if (routeName === 'GlobalMap' || routeName === 'Upload' || routeName === 'Leaderboard') {
//         showFullHeight.value = true;
//     } else {
//         // References.vue
//         showFullHeight.value = false;
//     }
// });

</script>
