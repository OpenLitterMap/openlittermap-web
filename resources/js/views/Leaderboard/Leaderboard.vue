<template>
    <section class="hero is-link is-bold h100">
        <section class="section is-link is-bold">
            <h3 class="title is-2 has-text-centered">
                {{ $t('location.global-leaderboard') }}
            </h3>

            <p v-if="loading">Loading...</p>

            <GlobalLeaders
                v-else
                :leaders="leaders"
                :show-pagination="true"
            />
        </section>
    </section>
</template>

<script>
import GlobalLeaders from "../../components/global/GlobalLeaders";

export default {
    name: "Leaderboard",
    components: {
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
         * Array of users from the leaderboard
         */
        leaders () {
            return this.$store.state.leaderboard.users ?? [];
        }
    }
}
</script>

<style scoped>

</style>
