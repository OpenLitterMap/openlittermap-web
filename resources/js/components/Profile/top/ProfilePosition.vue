<template>
    <div class="profile-position">
        <p class="profile-position-text">{{ usersPosition }}</p>
    </div>
</template>

<script>
import moment from 'moment';

export default {
    name: 'ProfilePosition',
    props: [
        'position'
    ],
    computed: {
        /**
         * The users position out of all users, based on their XP
         */
        usersPosition ()
        {
            return moment.localeData().ordinal(this.position);
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
