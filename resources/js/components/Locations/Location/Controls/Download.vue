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

        <div class="flex flex-col items-start gap-3 mb-4 max-w-md mx-auto text-left">
            <span class="text-sm font-semibold uppercase tracking-wider text-gray-600">{{ t('Format') }}</span>
            <div>
                <label class="flex items-center gap-1 text-sm font-medium text-gray-700 cursor-pointer">
                    <input type="radio" value="wide" v-model="layout" class="h-4 w-4" />
                    {{ t('Number-based') }}
                    <span
                        v-tooltip="t('Each photo is one row. Tags are counted in columns — for example, the ALCOHOL.bottle column shows how many alcohol bottles were in the photo. Most cells will be empty since most tags don\'t apply to every photo.')"
                        :title="t('Each photo is one row. Tags are counted in columns — for example, the ALCOHOL.bottle column shows how many alcohol bottles were in the photo. Most cells will be empty since most tags don\'t apply to every photo.')"
                        class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                        aria-label="Number-based help"
                    >ⓘ</span>
                </label>
                <p class="ml-6 mt-0.5 text-xs text-gray-500 leading-snug">{{ t('One row per photo. Counts how many of each tag appear in columns. Best for browsing in Excel or Google Sheets.') }}</p>
            </div>
            <div class="ml-6 flex flex-col gap-2" :class="layout === 'long' ? 'opacity-50' : ''">
                <div>
                    <label class="flex items-center gap-1 text-sm text-gray-700" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                        <input type="checkbox" v-model="formatSplit" :disabled="layout === 'long'" class="h-4 w-4" />
                        {{ t('Separate columns') }}
                        <span
                            v-tooltip="t('Object, type, and material each get their own column. Recommended for new analyses.')"
                            :title="t('Object, type, and material each get their own column. Recommended for new analyses.')"
                            class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                            aria-label="Separate columns help"
                        >ⓘ</span>
                    </label>
                    <p class="ml-6 text-xs text-gray-500 leading-snug">{{ t('Object, type, and material in their own columns.') }}</p>
                </div>
                <div>
                    <label class="flex items-center gap-1 text-sm text-gray-700" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                        <input type="checkbox" v-model="formatJoined" :disabled="layout === 'long'" class="h-4 w-4" />
                        {{ t('Combined columns') }}
                        <span
                            v-tooltip="t('Object and type are joined into a single column name like spirits_bottle. Use this if your existing scripts expect the v4 column layout.')"
                            :title="t('Object and type are joined into a single column name like spirits_bottle. Use this if your existing scripts expect the v4 column layout.')"
                            class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                            aria-label="Combined columns help"
                        >ⓘ</span>
                    </label>
                    <p class="ml-6 text-xs text-gray-500 leading-snug">{{ t('Object and type joined (e.g. spirits_bottle). Use this if your existing scripts expect the v4 layout.') }}</p>
                </div>
            </div>
            <div>
                <label class="flex items-center gap-1 text-sm font-medium text-gray-700 cursor-pointer">
                    <input type="radio" value="long" v-model="layout" class="h-4 w-4" />
                    {{ t('Full-detail (one row per tag)') }}
                    <span
                        v-tooltip="t('Each tag becomes a row. A photo with 3 different tags produces 3 rows, with photo details repeated. The photo_tag_id column lets you group rows back together. Best for analysis tools.')"
                        :title="t('Each tag becomes a row. A photo with 3 different tags produces 3 rows, with photo details repeated. The photo_tag_id column lets you group rows back together. Best for analysis tools.')"
                        class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                        aria-label="Full-detail help"
                    >ⓘ</span>
                </label>
                <p class="ml-6 mt-0.5 text-xs text-gray-500 leading-snug">{{ t('One row per tag, with photo details repeated. Each material, brand, and custom tag becomes its own row. Best for pandas, SQL, Tableau, or any analysis tool.') }}</p>
            </div>
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
import { ref, computed, watch } from 'vue';
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

watch(layout, (next) => {
    if (next === 'wide' && !formatSplit.value && !formatJoined.value) {
        formatSplit.value = true;
    }
});

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
    } catch (err) {
        downloadSuccess.value = false;
        if (err?.response?.status === 429) {
            const retry = err.response.headers?.['retry-after'];
            downloadMessage.value = retry
                ? t('Too many exports — try again in {seconds}s.', { seconds: retry })
                : t('Too many exports — try again in a moment.');
        } else {
            downloadMessage.value = t('Export failed. Please try again.');
        }
    } finally {
        downloading.value = false;
    }
}
</script>
