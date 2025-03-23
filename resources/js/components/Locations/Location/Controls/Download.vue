<template>
    <div class="text-center">
        <h1 class="text-2xl font-bold">{{ t('location.download-open-verified-data') }}</h1>
        <h1 class="text-2xl font-bold">{{ t('location.stop-plastic-ocean') }}</h1>

        <p class="mb-1" v-if="!isAuth">{{ t('location.enter-email-sent-data') }}</p>

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

        <button
            :disabled="disableDownloadButton"
            class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded text-lg mb-4 disabled:opacity-50"
            @click="download"
        >
            {{ t('common.download') }}
        </button>

        <p>&copy; OpenLitterMap & Contributors.</p>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
// Import your Pinia stores (adjust the paths/names as needed)
import { useUserStore } from '@/stores/user';
// import { useDownloadStore } from '@/stores/download';

// Define props
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

// Setup i18n
const { t } = useI18n();

// Local state for email and whether a valid email was entered
const email = ref('');
const emailEntered = ref(false);

// Access the Pinia stores
const userStore = useUserStore();
// const downloadStore = useDownloadStore();

// Computed: whether the user is authenticated
const isAuth = computed(() => userStore.auth);

// Computed: disable the download button if not authenticated and a valid email has not been entered
const disableDownloadButton = computed(() => (isAuth.value ? false : !emailEntered.value));

// Validate the email input using a regex
function textEntered() {
    const regexEmail = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    emailEntered.value = regexEmail.test(email.value);
}

// Download method: dispatches a download action from the download store
function download() {
    // await downloadStore.downloadData({
    //     locationType: props.locationType,
    //     locationId: props.locationId,
    //     email: email.value,
    // });

    alert('todo');

    // Reset the email input after the download is requested
    email.value = '';
    emailEntered.value = false;
}
</script>
