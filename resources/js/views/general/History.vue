<template>
    <div>
        <loading
            v-if="loading"
            :active="true"
            :is-full-page="true"
        />

        <LitterTable
            v-else
            title="All Uploads"
            :paginatedPhotos="paginatedPhotos"
            action="GET_ALL_PHOTOS_PAGINATED"
        />
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay';
import 'vue-loading-overlay/dist/vue-loading.css'
import LitterTable from "../../components/Litter/LitterTable";

export default {
    name: "History",
    components: {
        Loading,
        LitterTable
    },
    data () {
        return {
            loading: true
        };
    },
    async created () {
        await this.$store.dispatch('GET_ALL_PHOTOS_PAGINATED');

        await this.$store.dispatch('GET_LIST_OF_COUNTRY_NAMES');

        this.loading = false;
    },
    computed: {
        paginatedPhotos () {
            return this.$store.state.alldata.paginated;
        }
    }
}
</script>

<style scoped>

</style>
