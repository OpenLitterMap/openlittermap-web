<template>
    <section class="profile-container">

        <!--  Todo - Show Loading -->
        <!--  Todo - Add translations -->

        <!-- Column 1, Row 1 -->
        <div class="profile-card">
            <p class="mb1">Welcome to your new Profile, {{ name }}</p>

            <p class="mb1">Out of {{ totalUsers }} users</p>

            <p>You are currently in {{ usersPosition }} place</p>
        </div>

        <!-- Column 1, Row 2 -->
        <div class="profile-card">
            <p>Total Photos, Total Litter, Top 5 items</p>
        </div>

        <!-- Column 1, Row 3 -->
        <div class="profile-card">
            <p>Next Target, Progress</p>
        </div>

        <!-- Column 2, Row 1 -->
        <div class="profile-card">
            <p>Map controls</p>
        </div>

        <!-- Column 2, Row 2 -->
        <div class="profile-card">
            <p>Insert Map</p>
        </div>

        <!-- Column 2, Row 3 -->
        <div class="profile-card">
            <p>Gamification Awards</p>
        </div>

        <!-- Column 3, Row 1 -->
        <div class="profile-card">
            <p>Time-series controls?</p>
        </div>

        <!-- Column 3, Row 2 -->
        <div class="profile-card">
            <p>Time-series chart of the users data</p>
        </div>

        <!-- Column 3, Row 3 -->
        <div class="profile-card">
            <p>Hello world</p>
        </div>

    </section>
</template>

<script>
import moment from 'moment';

export default {
    name: 'Profile',
    async created ()
    {
        await this.$store.dispatch('GET_USERS_POSITION');
    },
    computed: {

        /**
         * The users name
         */
        name ()
        {
            return this.user.user.name;
        },

        /**
         * The total number of accounts on OLM
         */
        totalUsers ()
        {
            return this.user.totalUsers;
        },

        /**
         * The users position out of all users, based on their XP
         */
        usersPosition ()
        {
            return moment.localeData().ordinal(this.user.position);
        },

        /**
         * The currently active user
         */
        user ()
        {
            return this.$store.state.user;
        }
    }
}
</script>

<style scoped>

    .profile-container {
        min-height: calc(100vh - 82px);
        background-color: #341f97;
        display: grid;
        grid-template-columns: 1fr 2fr 1fr;
        grid-template-rows: 1fr 2fr 1fr;
        column-gap: 1em;
        row-gap: 1em;
        padding: 3em;
    }

    .profile-card {
        background-color: #292f45;
        border-radius: 6px;
        box-shadow: 0 0.5em 1em -0.125em rgb(10 10 10 / 10%), 0 0px 0 1px rgb(10 10 10 / 2%);
        color: #4a4a4a;
        display: block;
        padding: 1.25rem;
    }

    .profile-card p {
        color: white;
    }
</style>
