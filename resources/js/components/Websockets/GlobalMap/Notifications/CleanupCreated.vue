<template>
    <GlobalMapNotification color="green">
        <template #image>
            <div class="-mt-2">
                <i class="fa fa-users fa-fw" />
            </div>
        </template>
        <template #content>
            <p>Cleanup created</p>
            <i>{{ t('Say hello to') }} <strong>{{ props.payload.name }}</strong>!</i>
        </template>
    </GlobalMapNotification>
</template>

<script setup>
import { defineProps } from 'vue';
import { useI18n } from 'vue-i18n';
import GlobalMapNotification from "./GlobalMapNotification.vue";
const { t } = useI18n();
const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<style scoped>
</style>
