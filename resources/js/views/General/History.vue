<template>
    <div>
        <h1>History</h1>
<!--        <LitterTable-->
<!--            v-else-->
<!--            title="All Uploads"-->
<!--            :paginatedPhotos="paginatedPhotos"-->
<!--            action="GET_ALL_PHOTOS_PAGINATED"-->
<!--        />-->
    </div>
</template>

<script setup>
import { computed, onMounted } from "vue";
import { useLoading } from 'vue-loading-overlay';
const $loading = useLoading();

// import LitterTable from "../../../old_js/components/Litter/LitterTable.vue";

onMounted(() => {
    const loader = $loading.show({container: null});

    // await this.$store.dispatch('GET_ALL_PHOTOS_PAGINATED');
    // await this.$store.dispatch('GET_LIST_OF_COUNTRY_NAMES');

    // loader.hide();
});

//  this.$store.state.alldata.paginated;
const paginatedPhotos = computed(() => []);

</script>

<style scoped>

</style>
