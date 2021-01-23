<template>
    <div class="profile-card">
        <Radar :ppm="ppm" />
    </div>
</template>

<script>
import Radar from '../../Charts/Radar';

export default {
    name: 'ProfileCategories',
    components: { Radar },
    computed: {

        /**
         * The users photos per month, as a string. Saved as metadata because CPU.
         */
        ppm ()
        {
            return this.$store.state.user.user.photos_per_month;
        }
    }
};
</script>

<style scoped>

</style>
