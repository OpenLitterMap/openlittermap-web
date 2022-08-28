<template>
    <div class="profile-card">
        <p class="mb1">
            {{ $t('profile.dashboard.welcome') }},
            {{ name }}
        </p>

        <p class="mb1">
            {{ $t('profile.dashboard.out-of', {total: totalUsers} ) }}
        </p>

        <p class="mb1 is-secondary">
            {{ $t('profile.dashboard.rank', { rank: usersPosition } ) }}
        </p>

        <p class="mb1">
            Next Littercoin: {{ this.user.user.littercoin_progress }}%
        </p>

        <p>
            Total Littercoin: {{ this.user.user.total_littercoin }}
        </p>
    </div>
</template>

<script>
import moment from 'moment';

export default {
    name: 'ProfileWelcome',
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
         * Shortcut to User.js
         *
         * User.js contains a user object.
         */
        user ()
        {
            return this.$store.state.user;
        }
    }
};
</script>

<style scoped>

</style>
