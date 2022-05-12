<template>
    <section class="hero is-link is-bold is-fullheight">
        <section class="section is-link is-bold">

            <div class="leaderboard-heading"
                 @click="openWorldCup"
            >
                <i class="fa fa-arrow-left"/>
                <h3 class="title is-2 has-text-centered">
                    {{ $t('location.global-leaderboard') }}
                </h3>
            </div>

            <loading
                v-if="loading"
                :active="true"
                :is-full-page="true"
                background-color="transparent"
                color="white"
            />

            <div v-if="!loading">
                <GlobalLeaders
                    :leaders="leaderboard.users"
                />

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
            window.scrollTo({top: 0, behavior: 'smooth'});
            await this.$store.dispatch('GET_PREVIOUS_LEADERBOARD_PAGE');
            this.loading = false;
        },

        async loadNextPage () {
            this.loading = true;
            window.scrollTo({top: 0, behavior: 'smooth'});
            await this.$store.dispatch('GET_NEXT_LEADERBOARD_PAGE');
            this.loading = false;
        },

        /**
         * Navigate to the World Cup page
         */
        openWorldCup () {
            this.$router.push({ path: '/world' });
        }
    }
}
</script>

<style lang="scss" scoped>
.leaderboard-heading {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    cursor: pointer;

    &:hover {
        .title {
            text-decoration: underline;
        }
        i {
            transform: translateX(-1rem);
        }
    }

    .title {
        margin-bottom: 0;
    }

    i {
        font-size: 20px;
        transition: all 0.3s;
    }
}
</style>
