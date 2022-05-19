<template>
    <section class="global-meta-data hero is-bold">
        <section class="wrapper is-link is-bold">

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
.global-meta-data {
    background-image: radial-gradient(circle at 1% 1%, #00d1b2, rgba(40, 180, 133, 1));
}
.wrapper {
    padding: 1rem 0.5rem;
}
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
</style>
