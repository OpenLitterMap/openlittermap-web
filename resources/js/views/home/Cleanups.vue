<template>
    <div class="cleanups-container">
        <div
            class="cleanup-map"
            :class="creatingCleanup ? 'find-location' : ''"
        >
            <Supercluster
                activeLayer="cleanups"
            />
        </div>

        <CleanupSidebar
            class="cleanup-sidebar"
            :class="creatingCleanup ? 'find-location' : ''"
            :creatingCleanup="creatingCleanup"
        />
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
    async created () {
        await this.$store.dispatch('GET_CLEANUPS');
    },
    computed: {
        /**
         * Todo - change icon on the map when we are finding a location
         */
        creatingCleanup ()
        {
            return this.$store.state.cleanups.creating;
        }
    }
}
</script>

<style scoped>

    .cleanups-container {
        height: calc(100% - 72px);
        display: flex;
    }

    .cleanup-map {
        flex: 0.7;
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
