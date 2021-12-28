<template>
    <div class="team-map-container">
        <loading v-if="loading && teamId > 0" :active.sync="loading" :is-full-page="false"/>

        <fullscreen
            v-if="teamId > 0"
            ref="fullscreen"
            @change="fullscreenChange"
            class="profile-map-container"
        >
            <button class="btn-map-fullscreen" @click="toggle">
                <i class="fa fa-expand"/>
            </button>
            <cluster-map
                :clusters-url="`/teams/clusters/${teamId}`"
                :points-url="`/teams/points/${teamId}`"
                @loading-complete="loading = false"
            />
        </fullscreen>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay';
import 'vue-loading-overlay/dist/vue-loading.css';
import ClusterMap from '../../views/global/ClusterMap';

export default {
    name: 'TeamMap',
    props: ['teamId'],
    components: {
        Loading,
        ClusterMap
    },
    data() {
        return {
            loading: true
        }
    },
    watch: {
        teamId (id)
        {
            if (id > 0) this.loading = true;
        }
    },
    methods: {
        fullscreenChange (fullscreen)
        {
            this.fullscreen = fullscreen;
        },

        toggle ()
        {
            this.$refs['fullscreen'].toggle();
        },
    }
};
</script>

<style lang="scss" scoped>
    @import '../../styles/variables.scss';

    .btn-map-fullscreen {
        position: absolute;
        top: 80px;
        left: 12px;
        z-index: 1234;
    }

    /* remove padding on mobile */
    .team-map-container {
        height: 750px;
        margin: 0;
        position: relative;
        padding-top: 1em;
    }

    @include media-breakpoint-down(lg) {
        .team-map-container {
            height: 500px;
        }
    }

    @include media-breakpoint-down(sm) {
        .team-map-container {
            margin-left: -3em;
            margin-right: -3em;
        }
    }
</style>
