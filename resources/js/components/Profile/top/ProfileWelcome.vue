<template>
    <div class="profile-card">
        <p class="mb1">{{ $t('profile_dashboard.top.welcome') }}, {{ name }}</p>

        <p class="mb1">{{ $t('profile_dashboard.top.out-of') }} {{ totalUsers }} {{ $t('profile_dashboard.top.users') }}</p>

        <p>{{ $t('profile_dashboard.top.in-position') }} {{ usersPosition }} {{ $t('profile_dashboard.top.place') }}</p>
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

</style>
