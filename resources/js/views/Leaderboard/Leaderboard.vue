<template>
    <section class="is-link hero is-bold is-fullheight">
        <section class="wrapper is-link is-bold">

            <div
                class="leaderboard-heading"
                @click="openWorldCup"
            >
                <i class="fa fa-arrow-left has-text-white"/>
                <h3 class="title is-2 has-text-centered has-text-white">
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
                <LeaderboardList
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
import Loading from "vue-loading-overlay";
import LeaderboardList from "../../components/global/LeaderboardList";

export default {
    name: "Leaderboard",
    components: {
        Loading,
        LeaderboardList
    },
    data () {
        return {
            loading: true
        };
    },
    async created () {
        this.loading = true;

        await this.$store.dispatch('GET_USERS_FOR_GLOBAL_LEADERBOARD', 'today');

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
        async loadPreviousPage ()
        {
            this.loading = true;

            window.scrollTo({top: 0, behavior: 'smooth'});

            await this.$store.dispatch('GET_PREVIOUS_LEADERBOARD_PAGE');

            this.loading = false;
        },

        async loadNextPage ()
        {
            this.loading = true;

            window.scrollTo({top: 0, behavior: 'smooth'});

            await this.$store.dispatch('GET_NEXT_LEADERBOARD_PAGE');

            this.loading = false;
        },

        /**
         * Navigate to the World Cup page
         */
        openWorldCup ()
        {
            this.$router.push({ path: '/world' });
        }
    }
}
</script>

<style lang="scss" scoped>

    .wrapper {
        padding: 1rem 0.5rem;
    }

    .leaderboard-heading {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
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

    @media screen and (min-width: 768px) {
        .wrapper {
            padding: 3rem 1.5rem;
        }
    }

</style>
