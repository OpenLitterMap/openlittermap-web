<template>
    <div class="cmc">
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <CityMap v-else />
    </div>
</template>

<script>
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

        let split = window.location.href.split('/'); // [6];

        let min = null;
        let max = null;
        let hex = null;

        if (split.length === 11)
        {
            min = split[8];
            max = split[9];
            hex = split[10];
        }

        await this.$store.dispatch('GET_CITY_DATA', {
            city: split[6],
            min,
            max,
            hex
        });

        this.loading = false;
    }
}
</script>

<style scoped>

    .cmc {
        height: calc(100vh - 82px);
    }
</style>
