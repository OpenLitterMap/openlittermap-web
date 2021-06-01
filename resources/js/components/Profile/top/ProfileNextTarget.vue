<template>
    <div class="profile-card">
        <p class="mb1">{{ $t('profile_dashboard.top.your-level') }}</p>

        <p class="is-purp">{{ $t('profile_dashboard.top.reached-level') }} <strong class="is-white">{{ currentLevel }}</strong></p>

        <p class="is-purp mb1">{{ $t('profile_dashboard.top.have-xp') }} <strong class="is-white">{{ currentXp }} xp</strong></p>

        <p class="is-purp mb2">{{ $t('profile_dashboard.top.need-xp') }} <strong class="is-white">{{ neededXp }} xp</strong> {{ $t('profile_dashboard.top.to-reach-level') }}</p>
    </div>
</template>

<script>
export default {
    name: 'ProfileNextTarget',
    computed: {

        /**
         * The users current level, based on their XP
         */
        currentLevel ()
        {
            return this.user.level;
        },

        /**
         * The users current XP
         */
        currentXp ()
        {
            return this.user.xp;
        },

        /**
         * Remaining xp until the user Levels Up
         */
        neededXp ()
        {
            return this.$store.state.user.requiredXp;
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

    .is-purp {
        color: #8e7fd6;
    }

    .is-white {
        color: white !important;
    }
</style>
