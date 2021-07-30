<template>
    <div class="locations-container">
        <section class="hero is-info is-medium">
            <div class="hero-body">
                <div class="container">
                    <div class="columns">
                        <div class="column is-4">
                            <h1 class="title is-1 flex pointer" @click="goBack">
                                <i v-show="!loading" class="fa fa-chevron-left country-back" />
                                {{ countryName }}
                            </h1>
                            <h1 class="subtitle is-3">{{ stateName }}</h1>
                        </div>

                        <!-- Todo - put Country & State metadata here -->
                    </div>
                </div>
            </div>
        </section>

        <sort-locations locationType="city" />
    </div>
</template>

<script>
import SortLocations from './SortLocations'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
    name: 'Cities',
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_CITIES', {
            country: window.location.href.split('/')[4],
            state: window.location.href.split('/')[5]
        });

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
         * The parent Country
         */
        countryName ()
        {
            return this.$store.state.locations.countryName;
        },

        /**
         * The parent State
         */
        stateName ()
        {
            return this.$store.state.locations.stateName;
        }
    },
    methods: {
        /**
         * Go to country and load States
         */
        goBack ()
        {
            this.$store.commit('setLocations', []);

            return this.$router.push({ path: '/world/' + this.countryName });
        }
    }
}
</script>

<style scoped>

</style>
