<template>
    <div>
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <CityMap v-else />
    </div>
</template>

<script>
/* We need to wrap the map in a container */
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import CityMap from './CityMap'

export default {
    name: 'CityMapContainer',
    components: {
        Loading,
        CityMap
    },
    data ()
    {
        return {
            loading: true
        };
    },
    async created ()
    {
        this.loading = true;

        let city = window.location.href.split('/')[6];
        await this.$store.dispatch('GET_CITY_DATA', city);

        this.loading = false;
    }
}
</script>

<style scoped>

</style>
