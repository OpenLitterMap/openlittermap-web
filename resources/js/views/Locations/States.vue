<template>
    <div class="locations-container">
        <section class="hero is-info is-medium">
            <div class="hero-body">
                <div class="container">
                    <div class="columns">
                        <div class="column is-4">
                            <h1 class="title is-1 flex pointer" @click="goBack">
                                <i v-show="!loading" class="fa fa-chevron-left country-back" />
                                {{ backButtonText }}
                            </h1>
                        </div>

                        <!-- Todo - put country metadata here -->
                    </div>
                </div>
            </div>
        </section>

        <sort-locations locationType="state" />
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

        window.scroll({ top: 0, left: 0 });

        const countryText = window.location.href.split('/')[4]

        await this.$store.dispatch('GET_STATES', countryText);

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
        backButtonText ()
        {
            return this.$store.state.locations.countryName;
        }
    },
    methods: {
        /**
         * Return to countries
         */
        goBack ()
        {
            this.$store.commit('setLocations', []);

            this.$router.push({ path: '/world' });
        }
    }
}
</script>

<style scoped>

</style>
