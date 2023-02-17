<template>
    <section class="is-link hero is-bold">
        <section class="wrapper is-link is-bold">

            <!-- Global Leaderboard -->
            <div class="container">

                <div class="flex jc">
                    <vue-typed-js
                        :strings="['Community', 'Impact', 'Progress']"
                        :loop="true"
                        :typespeed="5"
                        :startDelay="3000"
                        :backSpeed="1"
                        :fadeOut="true"
                        :fadeOutDelay="1500"
                    >
                        <h1 class="worldcup-title">Our Global <span class="typing"></span></h1>
                    </vue-typed-js>
                </div>

                <div class="leaderboard-heading"
                     @click="openLeaderboard"
                >
                    <h3 class="title is-2 has-text-centered">
                        {{ $t('location.global-leaderboard') }}
                    </h3>

                    <i class="fa fa-arrow-right"/>
                </div>

                <GlobalLeaders
                    :leaders="leaders"
                />
            </div>

            <Progress
                :loading="loading"
            />

        </section>
    </section>
</template>

<script>
import GlobalLeaders from '../global/GlobalLeaders'
import Progress from "../General/Progress";

export default {
    name: "GlobalMetaData",
    props: [
        'loading'
    ],
    components: {
        GlobalLeaders,
        Progress
    },
    channel: 'main',
    echo: {
        'ImageUploaded': (payload, vm) => {
            if (payload.isUserVerified) {
                vm.$store.commit('incrementTotalPhotos');
            }
        },
        'ImageDeleted': (payload, vm) => {
            if (payload.isUserVerified) {
                vm.$store.commit('decrementTotalPhotos');
            }
        },
        'TagsVerifiedByAdmin': (payload, vm) => {
            vm.$store.commit('incrementTotalLitter', payload.total_litter_all_categories);

            // If the user is verified
            // totalPhotos has been incremented during ImageUploaded
            if (!payload.isUserVerified) {
                vm.$store.commit('incrementTotalPhotos');
            }
        }
    },
    computed: {
        /**
         * The top-10 array of leaders
         */
        leaders ()
        {
            return this.$store.state.locations.globalLeaders;
        }
    },
    methods: {
        /**
         * Navigate to the Leaderboard page
         */
        openLeaderboard (view) {
            this.$router.push({ path: '/leaderboard' });
        }
    }
}
</script>

<style lang="scss" scoped>
    .wrapper {
        padding: 1rem 0.5rem;
    }

    .worldcup-title {
        font-size: 75px;
        margin-bottom: 15px;
        text-align: center;
        font-weight: 800;
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
                transform: translateX(1rem);
            }
        }

        .title {
            color: white;
            margin-bottom: 0;
        }

        i {
            color: white;
            font-size: 20px;
            transition: all 0.3s;
        }
    }

    @media screen and (min-width: 768px) {
        .wrapper {
            padding: 3rem 1.5rem;
        }
    }

    // Mobile view
    @media screen and (max-width: 768px) {
        .worldcup-title {
            font-size: 40px !important;
        }

        .leaderboard-heading h3 {
            font-size: 30px;
        }
    }

</style>
