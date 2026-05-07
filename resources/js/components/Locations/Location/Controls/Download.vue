<template>
    <div class="text-center">
        <h1 class="text-2xl font-bold">{{ t('Free and Open Verified Citizen Science Data on Plastic Pollution.') }}</h1>
        <h1 class="text-2xl font-bold">{{ t("Let's stop plastic going into the ocean.") }}</h1>

        <p v-if="!isAuth" class="my-4 text-gray-700">
            {{ t('Please sign in to download location data. The download link will be sent to your account email.') }}
        </p>

        <button
            v-if="isAuth"
            class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded text-lg my-4 disabled:opacity-50"
            :class="{ 'ring-2 ring-red-300 ring-offset-1': drawerOpen }"
            @click="toggleDrawer"
        >
            {{ t('Download') }}
        </button>

        <ExportDrawer
            v-if="isAuth"
            scope="location"
            theme="light"
            :scope-id="locationId"
            :open="drawerOpen"
            :queued="drawerQueued"
            :exporting="downloading"
            :photo-count="photoCount"
            :email="userEmail"
            class="text-left max-w-md mx-auto rounded"
            @export="download"
            @cancel="onDrawerCancel"
        />

        <p v-if="downloadError" class="text-sm my-3 text-red-600">{{ downloadError }}</p>

        <p class="mt-4">&copy; OpenLitterMap & Contributors.</p>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useUserStore } from '@/stores/user';
import ExportDrawer from '@/components/ExportDrawer.vue';

const props = defineProps({
    locationType: { type: String, required: true },
    locationId: { type: [String, Number], required: true },
    photoCount: { type: Number, default: 0 },
});

const { t } = useI18n();

const downloading = ref(false);
const downloadError = ref('');
const drawerOpen = ref(false);
const drawerQueued = ref(false);

const userStore = useUserStore();
const isAuth = computed(() => userStore.auth);
const userEmail = computed(() => userStore.user?.email ?? '');

const toggleDrawer = () => {
    if (drawerOpen.value) {
        drawerOpen.value = false;
        drawerQueued.value = false;
        downloadError.value = '';
    } else {
        drawerOpen.value = true;
    }
};

const onDrawerCancel = () => {
    drawerOpen.value = false;
    drawerQueued.value = false;
    downloadError.value = '';
};

async function download({ layout, format }) {
    downloading.value = true;
    downloadError.value = '';

    try {
        await axios.post('/api/download', {
            locationType: props.locationType,
            locationId: props.locationId,
            layout,
            format,
        });
        drawerQueued.value = true;
    } catch (err) {
        if (err?.response?.status === 429) {
            const retry = err.response.headers?.['retry-after'];
            downloadError.value = retry
                ? t('Too many exports — try again in {seconds}s.', { seconds: retry })
                : t('Too many exports — try again in a moment.');
        } else {
            downloadError.value = t('Export failed. Please try again.');
        }
    } finally {
        downloading.value = false;
    }
}
</script>
