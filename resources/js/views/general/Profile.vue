<template>
    <section class="outer-profile-container">

        <!--  Todo - Show Loading -->
        <!--  Todo - Later: Add translations -->
        <!--  Todo - Animate numbers incrementing from previous to current values -->
        <ProfileMap />

        <div class="main-profile-container">
            <ProfilePosition
                :position="this.userData.usersPosition"
            />

            <ProfileStats
                :photo-percent="this.userData.photoPercent"
                :photos-count="this.user.photos_count"
                :tag-percent="this.userData.tagPercent"
                :tags-count="this.user.total_tags"
            />

            <ProfileNextTarget
                :level="this.user.level"
                :xp="this.user.xp"
                :xp-needed="this.userData.requiredXp"
            />

            <div class="flex">

                <div style="flex: 0.1;" />

                <ProfileCalendar
                    v-if="canDownloadUserData"
                />

                <div style="flex: 0.25" />

                <ProfileCategories />

                <div class="smaller-empty-profile-card" />
            </div>

            <ProfileTimeSeries
                :ppm="this.user.photos_per_month"
            />

            <div class="profile-buttons-container">
                <ProfileDownload
                    v-if="canDownloadUserData"
                />

                <!-- Hide this when viewing a public profile -->
                <ProfilePhotos
                    v-if="!publicProfile"
                />
            </div>
        </div>
    </section>
</template>

<script>
import ProfilePosition from '../../components/Profile/top/ProfilePosition';
import ProfileStats from '../../components/Profile/top/ProfileStats';
import ProfileNextTarget from '../../components/Profile/top/ProfileNextTarget';
import ProfileCategories from '../../components/Profile/middle/ProfileCategories';
import ProfileMap from '../../components/Profile/middle/ProfileMap';
import ProfileCalendar from '../../components/Profile/middle/ProfileCalendar';
import ProfileDownload from '../../components/Profile/bottom/ProfileDownload';
import ProfileTimeSeries from '../../components/Profile/bottom/ProfileTimeSeries';
import ProfilePhotos from '../../components/Profile/bottom/ProfilePhotos';

export default {
    name: 'Profile',
    components: {
        ProfilePosition,
        ProfileTimeSeries,
        ProfileStats,
        ProfileNextTarget,
        ProfileCategories,
        ProfileMap,
        ProfileCalendar,
        ProfileDownload,
        ProfilePhotos
    },
    async mounted ()
    {
        if (!this.publicProfile)
        {
            await this.$store.dispatch('GET_CURRENT_USER');
            await this.$store.dispatch('GET_USERS_PROFILE_DATA');
        }
    },
    computed: {
        /**
         * Return True if we can download the users data
         */
        canDownloadUserData ()
        {
            // Return True if the user is visiting their own profile
            if (!this.publicProfile) return true;

            return this.publicProfile.settings.public_profile_download_my_data;
        },

        /**
         * Publicly available data per user
         */
        publicProfile ()
        {
            return this.$store.state.user.public_profile.publicProfile;
        },

        /**
         * Return either the currently authenticated user,
         *
         * Or show another users public profile if it exists.
         */
        user ()
        {
            return (this.publicProfile)
                ? this.publicProfile
                : this.$store.state.user.user;
        },

        /**
         * Extra user data that is loaded separately to main user
         */
        userData ()
        {
            return this.$store.state.user.public_profile.userData;
        }
    }
}
</script>

<style lang="scss">

    .outer-profile-container {
        display: flex;
        flex-direction: column;
        min-height: calc(100vh - 82px);
    }

    .main-profile-container {
        background-color: #292f45;
        flex: 1;
    }

    .profile-card {
        flex: 1;
    }

    .profile-card p {
        color: white;
    }

    .smaller-empty-profile-card {
        flex: 0.25;
    }

    .profile-buttons-container {
        padding: 3em 10em;
        display: flex;
    }
</style>
