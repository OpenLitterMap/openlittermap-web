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

        <div class="flex flex-col items-start gap-2 mb-4 max-w-md mx-auto">
            <span class="text-sm font-semibold uppercase tracking-wider text-gray-600">{{ t('Format') }}</span>
            <label class="flex items-center gap-1 text-sm text-gray-700 cursor-pointer">
                <input type="radio" value="wide" v-model="layout" class="h-4 w-4" />
                {{ t('Wide format') }}
                <span
                    v-tooltip="t('One row per photo with a column for every possible tag. Easy to scan in Excel. Most cells will be empty.')"
                    :title="t('One row per photo with a column for every possible tag. Easy to scan in Excel. Most cells will be empty.')"
                    class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                    aria-label="Wide format help"
                >ⓘ</span>
            </label>
            <div class="ml-6 flex items-center gap-4" :class="layout === 'long' ? 'opacity-50' : ''">
                <label class="flex items-center gap-1 text-sm text-gray-700" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                    <input type="checkbox" v-model="formatSplit" :disabled="layout === 'long'" class="h-4 w-4" />
                    {{ t('Separate columns') }}
                    <span
                        v-tooltip="t('One column each for object, type, and material. Recommended for new analyses.')"
                        :title="t('One column each for object, type, and material. Recommended for new analyses.')"
                        class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                        aria-label="Separate columns help"
                    >ⓘ</span>
                </label>
                <label class="flex items-center gap-1 text-sm text-gray-700" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                    <input type="checkbox" v-model="formatJoined" :disabled="layout === 'long'" class="h-4 w-4" />
                    {{ t('Combined columns') }}
                    <span
                        v-tooltip="t('Object and type joined into one column (v4-style). Use this if your existing scripts expect columns like spirits_bottle.')"
                        :title="t('Object and type joined into one column (v4-style). Use this if your existing scripts expect columns like spirits_bottle.')"
                        class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                        aria-label="Combined columns help"
                    >ⓘ</span>
                </label>
            </div>
            <label class="flex items-center gap-1 text-sm text-gray-700 cursor-pointer">
                <input type="radio" value="long" v-model="layout" class="h-4 w-4" />
                {{ t('Long format') }}
                <span
                    v-tooltip="t('One row per tag, with photo details repeated. Each tag gets its own row. Best for analysis tools like pandas, SQL, or Tableau.')"
                    :title="t('One row per tag, with photo details repeated. Each tag gets its own row. Best for analysis tools like pandas, SQL, or Tableau.')"
                    class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                    aria-label="Long format help"
                >ⓘ</span>
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

const layout = ref('wide');
const formatSplit = ref(true);
const formatJoined = ref(false);

const userStore = useUserStore();

const isAuth = computed(() => userStore.auth);

const disableDownloadButton = computed(() => {
    if (downloading.value) return true;
    if (layout.value === 'wide' && !formatSplit.value && !formatJoined.value) return true;
    return isAuth.value ? false : !emailEntered.value;
});

function textEntered() {
    const regexEmail = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    emailEntered.value = regexEmail.test(email.value);
}

const buildFormatParam = () => {
    if (layout.value === 'long') return '';
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
            layout: layout.value,
            format: buildFormatParam(),
        };
        if (!isAuth.value) {
            payload.email = email.value;
        }

        await axios.post('/api/download', payload);
        downloadSuccess.value = true;
        downloadMessage.value = t('Export started — check your email for the download link.');
        email.value = '';
        emailEntered.value = false;
    } catch {
        downloadSuccess.value = false;
        downloadMessage.value = t('Export failed. Please try again.');
    } finally {
        downloading.value = false;
    }
}
</script>
