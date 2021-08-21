<template>
    <section class="profile-container">

        <!--  Todo - Show Loading -->
        <!--  Todo - Later: Add translations -->
        <!--  Todo - Animate numbers incrementing from previous to current values -->
        <ProfileMap />

        <ProfilePosition />

        <ProfileStats />

        <ProfileNextTarget />

        <ProfileCalendar />

        <div class="flex">
            <div class="empty-profile-card" />

            <ProfileCategories />

            <ProfileTimeSeries />

            <div class="smaller-empty-profile-card" />
        </div>

        <div class="profile-buttons-container">
            <ProfileDownload />

            <ProfilePhotos />
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
        console.log('profile created', this.publicProfile);

        if (!this.publicProfile)
        {
            await this.$store.dispatch('GET_CURRENT_USER');

            await this.$store.dispatch('GET_USERS_PROFILE_DATA');
        }
    },
    computed: {
        /**
         *
         */
        publicProfile ()
        {
            return this.$store.state.user.public_profile.publicProfile;
        },

        /**
         *
         */
        user ()
        {
            return true;
        }
    }
}
</script>

<style lang="scss">

    .profile-container {
        min-height: calc(100vh - 82px);
    }

    .profile-card {
        flex: 1;
        background-color: #292f45;
        padding: 1.25em;
    }

    .profile-card p {
        color: white;
    }

    .profile-stat-card p {

    }

    .empty-profile-card {
        background-color: #292f45;
        flex: 0.75;
    }

    .smaller-empty-profile-card {
        flex: 0.25;
        background-color: #292f45;
    }

    .profile-buttons-container {
        background-color: #292f45;
        padding: 9em 10em;
        display: flex;
    }
</style>
