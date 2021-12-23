<template>
    <div class="team-map-container">
        <loading v-if="loading" :active.sync="loading" :is-full-page="false"/>

        <fullscreen
            v-else-if="geojson"
            ref="fullscreen"
            @change="fullscreenChange"
            class="profile-map-container"
        >
            <button class="btn-map-fullscreen" @click="toggle">
                <i class="fa fa-expand"/>
            </button>
            <supercluster
                :clusters-url="'/global/clusters'"
                :points-url="'/global/points'"
            />
        </fullscreen>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay';
import 'vue-loading-overlay/dist/vue-loading.css';
import Supercluster from '../../views/global/Supercluster';

export default {
    name: 'TeamMap',
    components: {
        Loading,
        Supercluster
    },
    async created ()
    {
        this.attribution += new Date().getFullYear();

        await this.$store.dispatch('GET_CLUSTERS', 2);
        await this.$store.dispatch('GET_ART_DATA');

        this.$store.commit('globalLoading', false);
    },
    computed: {

        /**
         * From backend api request
         */
        geojson ()
        {
            return this.$store.state.teams.geojson
                ? this.$store.state.teams.geojson.features
                : [];
        },

        loading ()
        {
            return this.$store.state.globalmap.loading;
        },
    },

    methods: {

        /**
         * Return html content for each popup
         *
         * Translate tags
         *
         * Format datetime (time image was taken)
         */
        content (feature)
        {
            return mapHelper.getMapImagePopupContent(
                feature.properties.img,
                feature.properties.text,
                feature.properties.datetime,
                feature.properties.picked_up,
                '',
                ''
            );
        },

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

        .temp-info {
            text-align: center;
            margin-top: 1em;
        }
    }
</style>
