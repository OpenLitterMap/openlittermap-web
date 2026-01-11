<template>
    <div class="h-full">
        <Nav />

        <Modal />

        <router-view />
    </div>
</template>

<script setup>
import { computed, defineProps, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import { useUserStore } from './stores/user/index.js';
import { useUploadingStore } from './stores/uploading/index.js';

const userStore = useUserStore();
const uploadingStore = useUploadingStore();

const route = useRoute();
const routeName = computed(() => route.name);

const props = defineProps({
    auth: {
        type: Boolean,
        required: true,
        default: false,
    },
    user: {
        type: Object,
        required: false,
        default: null,
    },
    verified: {
        type: Boolean,
        required: false,
        default: false,
    },
    unsub: {
        type: Boolean,
        required: false,
        default: false,
    },
});

onMounted(() => {
    console.log('mounted', props);

    if (props.auth) {
        userStore.initUser(props.user);
    }

    if (props.verified) {
        userStore.SET_EMAIL_CONFIRMED(true);
    }

    if (props.unsub) {
        userStore.SET_UNSUBSCRIBED(true);
    }
});

const showFullHeight = ref(true);

userStore.CHECK_AUTH();
uploadingStore.setIsUploading(false);

// There is a bug when displaying the height on the upload page.
const isUploading = computed(() => uploadingStore.isUploading);

// watch([isUploading, () => route.name], ([isUploadingValue, routeName]) => {
//     if (routeName === 'GlobalMap' || routeName === 'Upload' || routeName === 'Leaderboard') {
//         showFullHeight.value = true;
//     } else {
//         // References.vue
//         showFullHeight.value = false;
//     }
// });
</script>
