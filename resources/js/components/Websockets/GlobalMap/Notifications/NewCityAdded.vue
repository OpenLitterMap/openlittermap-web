<template>
    <GlobalMapNotification color="blue">
        <!-- Image slot -->
        <template #image>
            <!-- Negative top margin replaced with Tailwind bracket notation -->
            <div class="-mt-[2px]">
                <i class="fa fa-flag fa-fw"></i>
            </div>
        </template>

        <!-- Content slot -->
        <template #content>
            <strong>{{ t('home.globalMap.new-city') }}</strong>
            <p>
                {{ t('home.globalMap.say-hello-to') }}
                <i>{{ payload.city }}</i>
            </p>
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
