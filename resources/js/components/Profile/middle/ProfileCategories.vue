<template>
    <div class="profile-card">
        <!-- Using :key helps to update the radar when tags change -->
        <Radar :key="user.total_tags" :categories="categories" />
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
        categories ()
        {
            return this.user.total_categories.values();
        },

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    }
};
</script>

<style scoped>

</style>
