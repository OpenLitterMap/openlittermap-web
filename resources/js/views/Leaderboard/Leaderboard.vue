<template>
    <section class="hero is-link is-bold is-fullheight">
        <section class="section is-link is-bold">
            <h3 class="title is-2 has-text-centered">
                {{ $t('location.global-leaderboard') }}
            </h3>

            <loading
                v-if="loading"
                :active="true"
                :is-full-page="true"
                background-color="transparent"
                color="white"
            />

            <div v-if="!loading">
                <GlobalLeaders :leaders="leaderboard.users"/>

                <!-- Pagination Buttons -->
                <div
                    v-if="leaderboard.users.length"
                    class="flex jc"
                >
                    <button
                        v-show="leaderboard.currentPage > 1"
                        class="button is-medium mr-1"
                        @click="loadPreviousPage"
                    >
                        {{ $t('common.previous') }}
                    </button>

                    <button
                        v-show="leaderboard.hasNextPage"
                        class="button is-medium"
                        @click="loadNextPage"
                    >
                        {{ $t('common.next') }}
                    </button>
                </div>
            </div>
        </section>
    </section>
</template>

<script>
import GlobalLeaders from "../../components/global/GlobalLeaders";
import Loading from "vue-loading-overlay";

export default {
    name: "Leaderboard",
    components: {
        Loading,
        GlobalLeaders
    },
    data () {
        return {
            loading: true
        };
    },
    async created () {
        this.loading = true;
        await this.$store.dispatch('GET_GLOBAL_LEADERBOARD');
        this.loading = false;
    },
    computed: {
        /**
         * Shortcut to leaderboard state
         */
        leaderboard () {
            return this.$store.state.leaderboard;
        }
    },

    methods: {
        async loadPreviousPage () {
            this.loading = true;
            await this.$store.dispatch('GET_PREVIOUS_LEADERBOARD_PAGE');
            window.scrollTo({top: 0, behavior: 'smooth'});
            this.loading = false;
        },

        async loadNextPage () {
            this.loading = true;
            await this.$store.dispatch('GET_NEXT_LEADERBOARD_PAGE');
            window.scrollTo({top: 0, behavior: 'smooth'});
            this.loading = false;
        }
    }
}
</script>

<style scoped>

</style>
