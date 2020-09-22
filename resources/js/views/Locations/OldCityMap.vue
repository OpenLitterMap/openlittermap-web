<template>
    <div>
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <TestCityMap v-else />
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import TestCityMap from "./TestCityMap";

export default {
    name: 'OldCityMap',
    components: {
        Loading,
        TestCityMap
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
