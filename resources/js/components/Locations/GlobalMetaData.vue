<template>
    <section class="hero is-link is-bold">
        <section class="section is-link is-bold">

            <!-- Global Leaderboard -->
            <div class="container">

                <div class="leaderboard-heading"
                     @click="openLeaderboard"
                >
                    <h3 class="title is-2 has-text-centered">
                        {{ $t('location.global-leaderboard') }}
                    </h3>

                    <i class="fa fa-arrow-right"/>
                </div>

                <GlobalLeaders :leaders="leaders"/>
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
            transform: translateX(1rem);
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
