<template>
    <section class="hero is-link is-bold">
        <section class="section is-link is-bold">

            <!-- Global Leaderboard -->
            <div class="container has-text-centered">
                <h3
                    @click="openLeaderboard"
                    class="title is-2 has-text-centered"
                >{{ $t('location.global-leaderboard') }}</h3>

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
import GlobalLeaders from '../../components/Global/GlobalLeaders'
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
         *
         */
        openLeaderboard (view) {
            this.$router.push({ path: '/leaderboard' });
        }
    }
}
</script>

<style scoped>

</style>
