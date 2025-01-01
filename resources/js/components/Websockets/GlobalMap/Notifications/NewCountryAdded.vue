<template>
    <GlobalMapNotification color="blue">
        <template #image>
            <div class="-mt-[2px]">
                <i class="fa fa-flag fa-fw" />
            </div>
        </template>
        <template #content>
            <strong>{{ t('home.globalMap.new-country') }}</strong>
            <p>{{ t('home.globalMap.say-hello-to') }} <i>{{ payload.country }}</i></p>
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
        default: null
    }
});
</script>

<style scoped>

</style>
