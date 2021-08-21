<template>
    <div class="profile-next-target">
        <p class="is-secondary">Level: <strong class="is-white">{{ currentLevel }}</strong></p>

        <p class="is-secondary">XP: <strong class="is-white">{{ currentXp }} xp</strong></p>

        <p class="is-secondary">Level up: <strong class="is-white">{{ neededXp }} xp</strong></p>
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

    .profile-next-target {
        position: absolute;
        top: 33em;
        left: 7em;
        z-index: 999;
    }

    .profile-next-target p {
        font-size: 1.25em;
    }

    .is-purp {
        color: #8e7fd6;
    }

    .is-white {
        color: white !important;
    }

    .is-secondary {
        color: #1DD3B0;
    }
</style>
