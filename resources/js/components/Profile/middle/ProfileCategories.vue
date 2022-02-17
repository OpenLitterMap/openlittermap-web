<template>
    <div class="profile-categories">
        <CategoriesPieChart
            :categories="categories"
        />
    </div>
</template>

<script>
import CategoriesPieChart from '../../Charts/CategoriesPieChart';

export default {
    name: 'ProfileCategories',
    components: {
        CategoriesPieChart
    },
    computed: {
        /**
         * The users photos per month, as a string. Saved as metadata because CPU.
         */
        categories ()
        {
            return [
                this.user.total_categories?.alcohol,
                this.user.total_brands_redis,
                this.user.total_categories?.coastal,
                this.user.total_categories?.coffee,
                this.user.total_categories?.dumping,
                this.user.total_categories?.food,
                this.user.total_categories?.industrial,
                this.user.total_categories?.other,
                this.user.total_categories?.sanitary,
                this.user.total_categories?.softdrinks,
                this.user.total_categories?.smoking,
            ];
        },

        /**
         * Return User object
         *
         * Currently authenticated user (Profile page) or Public Profile
         */
        user ()
        {
            return (this.$store.state.user.public_profile.publicProfile)
                ? this.$store.state.user.public_profile.publicProfile
                : this.$store.state.user.user;
        }
    }
};
</script>

<style scoped>

    .profile-categories {
        flex: 1;
        padding: 3em;
    }

</style>
