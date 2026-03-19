<template>
    <GlobalMapNotification color="littercoin">
        <template #image>
            <div class="flex items-center h-full">
                <img src="@/assets/icons/mining.png" class="w-8" alt="Mining"/>
            </div>
        </template>
        <template #content>
            <div class="flex items-center h-full">
                <div>
                    <p>{{ t('A Littercoin has been mined!') }}</p>
                    <i>{{ t('Reason') }}: <span class="font-semibold">{{ getLittercoinReason(payload.reason) }}</span></i>
                </div>
            </div>
        </template>
    </GlobalMapNotification>
</template>

<script setup>
import { defineProps } from 'vue';
import { useI18n } from 'vue-i18n';
import GlobalMapNotification from './GlobalMapNotification.vue';

const { t } = useI18n();

const props = defineProps({
    payload: {
        type: Object,
        default: null,
    },
});

const getLittercoinReason = (reason) => {
    if (reason === 'verified-box') {
        return t('100 OpenLitterAI boxes verified');
    } else if (reason === '100-images-verified') {
        return t('100 images verified');
    }
}
</script>

<style scoped>
</style>
