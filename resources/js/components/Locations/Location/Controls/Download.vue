<template>
    <div class="text-center">
        <h1 class="text-2xl font-bold">{{ t('Free and Open Verified Citizen Science Data on Plastic Pollution.') }}</h1>
        <h1 class="text-2xl font-bold">{{ t("Let's stop plastic going into the ocean.") }}</h1>

        <p class="mb-1" v-if="!isAuth">{{ t('Please enter an email address to which the data will be sent:') }}</p>

        <input
            v-if="!isAuth"
            class="border border-gray-300 rounded p-2 mb-4 text-base"
            placeholder="you@email.com"
            type="email"
            name="email"
            required
            v-model="email"
            @input="textEntered"
            autocomplete="email"
        />

        <div class="flex items-center justify-center gap-4 mb-4">
            <span class="text-sm font-semibold uppercase tracking-wider text-gray-600">{{ t('CSV Format') }}:</span>
            <label class="flex items-center gap-1 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" v-model="formatSplit" class="h-4 w-4" />
                {{ t('Split') }}
            </label>
            <label class="flex items-center gap-1 text-sm text-gray-700 cursor-pointer" :title="t('v4-style joined columns (e.g. spirits_bottle)')">
                <input type="checkbox" v-model="formatJoined" class="h-4 w-4" />
                {{ t('Joined') }}
            </label>
        </div>

        <button
            :disabled="disableDownloadButton"
            class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded text-lg mb-4 disabled:opacity-50"
            @click="download"
        >
            {{ t(downloading ? 'Sending...' : 'Download') }}
        </button>

        <p v-if="downloadMessage" class="text-sm mb-4" :class="downloadSuccess ? 'text-green-600' : 'text-red-600'">{{ downloadMessage }}</p>

        <p>&copy; OpenLitterMap & Contributors.</p>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useUserStore } from '@/stores/user';

const props = defineProps({
    locationType: {
        type: String,
        required: true,
    },
    locationId: {
        type: [String, Number],
        required: true,
    },
});

const { t } = useI18n();

const email = ref('');
const emailEntered = ref(false);
const downloading = ref(false);
const downloadMessage = ref('');
const downloadSuccess = ref(false);

const formatSplit = ref(true);
const formatJoined = ref(false);

const userStore = useUserStore();

const isAuth = computed(() => userStore.auth);

const disableDownloadButton = computed(() => {
    if (downloading.value) return true;
    if (!formatSplit.value && !formatJoined.value) return true;
    return isAuth.value ? false : !emailEntered.value;
});

function textEntered() {
    const regexEmail = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    emailEntered.value = regexEmail.test(email.value);
}

const buildFormatParam = () => {
    const parts = [];
    if (formatSplit.value) parts.push('split');
    if (formatJoined.value) parts.push('joined');
    return parts.join(',');
};

async function download() {
    downloading.value = true;
    downloadMessage.value = '';

    try {
        const payload = {
            locationType: props.locationType,
            locationId: props.locationId,
            format: buildFormatParam(),
        };
        if (!isAuth.value) {
            payload.email = email.value;
        }

        await axios.post('/api/download', payload);
        downloadSuccess.value = true;
        downloadMessage.value = t('Export started — check your email for the download link.');
    } catch {
        downloadSuccess.value = false;
        downloadMessage.value = t('Export failed. Please try again.');
    } finally {
        downloading.value = false;
        email.value = '';
        emailEntered.value = false;
    }
}
</script>
