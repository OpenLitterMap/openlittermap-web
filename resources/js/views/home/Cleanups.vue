<template>
    <div class="cleanups-container">

        <!-- Left: Sidebar -->
        <CleanupSidebar
            class="cleanup-sidebar"
            :class="creatingCleanup ? 'find-location' : ''"
            :creatingCleanup="creatingCleanup"
            :joiningCleanup="joiningCleanup"
        />

        <!-- Right: Global Map -->
        <div
            class="cleanup-map"
            :class="creatingCleanup ? 'find-location' : ''"
        >
            <div v-if="loading" />

            <Supercluster
                v-else
                activeLayer="merchants"
            />
        </div>
    </div>
</template>

<script>
import Supercluster from "../global/Supercluster";
import CleanupSidebar from "../../components/Cleanups/CleanupSidebar";

export default {
    name: "Cleanups",
    components: {
        Supercluster,
        CleanupSidebar
    },
    data () {
        return {
            loading: false
        };
    },
    async created () {
        this.loading = true;

        await this.$store.dispatch('GET_CLEANUPS');

        const r = this.$route;

        if (r.params.hasOwnProperty('invite_link'))
        {
            await this.$store.dispatch('JOIN_CLEANUP', {
                link: r.params.invite_link
            });
        }

        this.loading = false;
    },
    computed: {
        /**
         * Return True if the user is trying to join a new cleanup
         *
         * Todo - change icon on the map when we are finding a location
         */
        creatingCleanup ()
        {
            return this.$store.state.cleanups.creating;
        },

        /**
         * Return True if the user is trying to join a new cleanup
         */
        joiningCleanup ()
        {
            return this.$store.state.cleanups.joining;
        }
    }
}
</script>

<style scoped>

    /* Hide the map on mobile */
    @media screen and (max-width: 768px) {
        .cleanup-map {
            flex: 0 !important;
        }

        .cleanup-sidebar {
            flex: 1 !important;
        }
    }


    .cleanups-container {
        height: calc(100% - 72px);
        display: flex;
    }

    .cleanup-map {
        flex: 0.7;
        z-index: 1;
    }

    /*.find-location {*/
    /*    // cursor: url('https://65.media.tumblr.com/avatar_91989eab746d_96.png'), auto !important;*/
    /*}*/

    .cleanup-sidebar {
        background-color: white;
        height: 100%;
        flex: 0.3;
        text-align: center;
        padding-left: 1em;
        padding-right: 1em;
    }

</stylescoped>
