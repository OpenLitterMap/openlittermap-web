<template>
    <div class="profile-card">
        <Radar :categories="categories" />
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
                this.user.total_smoking,
                this.user.total_food,
                this.user.total_coffee,
                this.user.total_softdrinks,
                this.user.total_alcohol,
                this.user.total_other,
                this.user.total_coastal,
                this.user.total_sanitary,
                this.user.total_dumping,
                this.user.total_industrial,
                this.user.total_brands
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
