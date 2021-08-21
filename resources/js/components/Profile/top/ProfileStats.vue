<template>
    <div class="profile-stats">

        <div class="profile-stat-card flex-1-25" />

        <div class="profile-stat-card">
            <img src="/assets/icons/home/camera.png" />

            <div>
                <p class="profile-stat">{{ userPhotoCount }}</p>
                <p class="profile-text">{{ $t('profile.dashboard.photos') }}</p>
            </div>
        </div>

        <div class="profile-stat-card">
            <img src="/assets/icons/home/phone.png" />

            <div>
                <p class="profile-stat">{{ userTagsCount }}</p>
                <p class="profile-text">{{ $t('profile.dashboard.tags') }}</p>
            </div>
        </div>

        <div class="profile-stat-card">
            <p class="profile-percent">%</p>

            <div>
                <p class="profile-stat">{{ photoPercent }}</p>
                <p class="profile-text">{{ $t('profile.dashboard.all-photos') }}</p>
            </div>
        </div>

        <div class="profile-stat-card">
            <p class="profile-percent">%</p>

            <div>
                <p class="profile-stat">{{ tagPercent }}</p>
                <p class="profile-text">{{ $t('profile.dashboard.all-tags') }}</p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ProfileStats',
    computed: {
        /**
         *
         */
        photoPercent ()
        {
            return this.user.photoPercent;
        },

        /**
         *
         */
        tagPercent ()
        {
            return this.user.tagPercent;
        },

        /**
         * Total number of tags the user has submitted
         */
        userTagsCount ()
        {
            return this.user.user.total_tags;
        },

        /**
         * Total number of photos the user has uploaded
         */
        userPhotoCount ()
        {
            return this.user.user.total_images;
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

    .profile-stats {
        display: flex;
        background-color: #292f45;
        padding: 3em 0;
    }

    .profile-percent {
        font-size: 3em;
        font-weight: 600;
        margin-right: 0.5em;
    }

    .profile-stat-card {
        flex: 1;
        display: flex;
        text-align: center;
    }

    .profile-stat-card img {
        height: 5em;
        margin: auto 1em auto 0;
    }

    .profile-stat-card p {
        color: white;
        font-size: 2em;
        text-transform: capitalize;
    }

    .profile-stat {
        font-weight: 600;
        font-size: 2em;
    }

    .profile-text {
        color: #1DD3B0 !important;
}

</style>
