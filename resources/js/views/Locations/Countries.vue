<script setup>
import { useLoading } from 'vue-loading-overlay';
import { onMounted } from 'vue';

import { useWorldStore } from '../../stores/world/index.js';
import GlobalMetaData from '../../components/Locations/GlobalMetaData.vue';
import SortLocations from '../../components/Locations/SortLocations.vue';

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

        <SortLocations />
    </div>
</template>

<style scoped></style>
