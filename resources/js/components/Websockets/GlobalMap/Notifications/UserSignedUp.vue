<template>
    <GlobalMapNotification color="yellow">
        <!-- Image Slot -->
        <template #image>
            <div class="-mt-[2px]">
                <i class="fa fa-user fa-fw"></i>
            </div>
        </template>

        <!-- Content Slot -->
        <template #content>
            <strong>{{ t('home.globalMap.new-user') }}</strong>
            <p>{{ t('home.globalMap.user-signed-up') }}</p>
        </template>
    </GlobalMapNotification>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import GlobalMapNotification from './GlobalMapNotification.vue'
const { t } = useI18n()
</script>

<style scoped>
</style>
