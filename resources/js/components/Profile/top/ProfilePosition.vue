<template>
    <div class="profile-position">
        <p class="profile-position-text">{{ usersPosition }}</p>
    </div>
</template>

<script>
import moment from 'moment';

export default {
    name: 'ProfilePosition',
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
};
</script>

<style scoped>

    .profile-position {
        position: absolute;
        background-color: white;
        padding: 3em;
        top: 20em;
        left: 5em;
        z-index: 999;
        border-radius: 50%;
        border: 2px solid black;
    }

    .profile-position-text {
        font-size: 3em;
        font-weight: 700;
    }

</style>
