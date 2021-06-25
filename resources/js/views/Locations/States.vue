<template>
    <div style="height: 70%;">
        <section class="hero is-info is-medium">
            <div class="hero-body">
                <div class="container">
                    <div class="columns">
                        <div class="column is-4">
                            <h1 class="title is-1 flex pointer" @click="goBack"><i v-show="! loading" class="fa fa-chevron-left country-back" /> {{ country }}</h1>
                        </div>

                        <!-- Todo - put country metadata here -->
                        
                        
                    </div>
                </div>
            </div>
        </section>

        <sort-locations type="state" />
    </div>
</template>

<script>
import SortLocations from './SortLocations'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
    name: 'States',
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_STATES', window.location.href.split('/')[4]);

        this.loading = false;
    },
    components: {
        Loading,
        SortLocations
    },
    data ()
    {
        return {
            loading: true
        };
    },
    computed: {

        /**
         * The parent country for these States
         */
        country ()
        {
            return this.$store.state.locations.country;
        }
    },
    methods: {

        /**
         * Return to countries
         */
        goBack ()
        {
            this.$router.push({ path: '/world' });
        }
    }
}
</script>

<style scoped>

</style>
