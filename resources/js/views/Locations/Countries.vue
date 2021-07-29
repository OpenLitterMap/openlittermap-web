<template>
    <div>
        <loading v-show="loading" :active.sync="loading" :is-full-page="true" />

        <GlobalMetaData :loading="loading" />

        <SortLocations locationType="country" />
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

import GlobalMetaData from '../../components/Locations/GlobalMetaData';
import SortLocations from './SortLocations'

export default {
    name: 'Countries',
    components: {
        Loading,
        GlobalMetaData,
        SortLocations
    },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_COUNTRIES');

        this.loading = false;
    },
    data ()
    {
        return {
            loading: true
        };
    }
}
</script>
