<template>
    <div class="profile-card">
        <p class="mb1">You have uploaded</p>

        <div class="flex">

            <div class="profile-stat-card">
                <img src="/assets/icons/bronze-medal.svg" />

                <div>
                    <p class="profile-stat">{{ totalPhotos }}</p>
                    <p class="profile-text">Photos</p>
                </div>
            </div>

            <div class="profile-stat-card">
                <img src="/assets/icons/bronze-medal.svg" />

                <div>
                    <p class="profile-stat">{{ totalTags }}</p>
                    <p class="profile-text">Tags</p>
                </div>
            </div>

            <div class="profile-stat-card">
                <img src="/assets/icons/bronze-medal.svg" />

                <div>
                    <p class="profile-stat">{{ photoPercent }}%</p>
                    <p class="profile-text">% all photos</p>
                </div>
            </div>

            <div class="profile-stat-card">
                <img src="/assets/icons/bronze-medal.svg" />

                <div>
                    <p class="profile-stat">{{ tagPercent }}%</p>
                    <p class="profile-text">% all tags</p>
                </div>
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
         * Total number of photos the user has uploaded
         */
        totalPhotos ()
        {
            return this.user.totalPhotos;
        },

        /**
         * Total number of tags the user has submitted
         */
        totalTags ()
        {
            return this.user.totalTags;
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
