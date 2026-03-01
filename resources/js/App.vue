<template>
    <div class="h-full">
        <!-- Impersonation banner -->
        <div
            v-if="impersonating"
            class="bg-amber-500 text-black px-4 py-2 text-center text-sm font-semibold flex items-center justify-center gap-4 z-50"
        >
            <span>
                Viewing as {{ userStore.user?.username || userStore.user?.name || 'user' }}
                (#{{ userStore.user?.id }})
            </span>
            <button
                @click="stopImpersonating"
                class="px-3 py-1 bg-black/20 rounded hover:bg-black/30 transition-colors text-xs font-bold uppercase"
            >
                Return to Admin
            </button>
        </div>

        <Nav />

        <Modal />

        <router-view />
    </div>
</template>

<script setup>
import { computed, defineProps, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';

import { useUserStore } from './stores/user/index.js';
import { useUploadingStore } from './stores/uploading/index.js';

const toast = useToast();
const userStore = useUserStore();
const uploadingStore = useUploadingStore();

const route = useRoute();
const router = useRouter();
const routeName = computed(() => route.name);

const impersonating = ref(window.initialProps?.impersonating ?? false);

const stopImpersonating = async () => {
    try {
        await axios.post('/api/impersonate/stop');
        window.location.href = '/admin/users';
    } catch (e) {
        toast.error('Failed to stop impersonating');
    }
};

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
});

onMounted(() => {
    if (props.auth) {
        userStore.initUser(props.user);
    }

    // Handle email confirmation and unsubscribe via query params (redirected from backend)
    const params = new URLSearchParams(window.location.search);

    if (params.get('verified') === '1') {
        userStore.emailConfirmed = true;
        toast.success('Email verified! You can now upload photos.', { position: 'top-right' });
        router.replace({ path: route.path, query: {} });
    }

    if (params.get('unsub') === '1') {
        userStore.unsubscribed = true;
        toast.success('You have been unsubscribed from emails. You can re-subscribe in your profile settings.', { position: 'top-right' });
        router.replace({ path: route.path, query: {} });
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
