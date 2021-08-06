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
            return [
                this.user.total_categories.alcohol,
                this.user.total_brands_redis,
                this.user.total_categories.coastal,
                this.user.total_categories.coffee,
                this.user.total_categories.dumping,
                this.user.total_categories.food,
                this.user.total_categories.industrial,
                this.user.total_categories.other,
                this.user.total_categories.sanitary,
                this.user.total_categories.softdrinks,
                this.user.total_categories.smoking,
            ]
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
