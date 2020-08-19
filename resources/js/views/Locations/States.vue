<template>
    <div>
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <section v-else class="hero is-info is-medium">
            <div class="hero-body">
                <div class="container">
                    <div class="columns">
                        <div class="column is-half">
                            <h1 class="title is-1">{{ country }}</h1>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="! loading" class="hero is-primary">
            <sort-locations type="state" />
        </section>
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
        SortLocations,
        Loading
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
    }
}
</script>

<style scoped>

</style>