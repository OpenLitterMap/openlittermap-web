<script setup>
import { useLoading } from 'vue-loading-overlay';
import { onMounted } from 'vue';

import { useWorldStore } from '../../stores/world/index.js';
import GlobalMetaData from '../../components/Locations/GlobalMetaData.vue';

const $loading = useLoading();
const worldStore = useWorldStore();

onMounted(async () => {
    const loader = $loading.show({ container: null });

    await worldStore.GET_WORLD_CUP_DATA();

    loader.hide();
});
</script>

<template>
    <div>
        <GlobalMetaData />
    </div>
</template>

<style scoped></style>
