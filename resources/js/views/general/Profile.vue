<template>
    <section class="profile-container">

        <!--  Todo - Show Loading -->
        <!--  Todo - Later: Add translations -->
        <!--  Todo - Animate numbers incrementing from previous to current values -->

        <!-- Row 1, Column 1 -->
        <ProfileWelcome />

        <!-- Row 1, Column 2 -->
        <ProfileStats />

        <!-- Row 1, Column 3 -->
        <ProfileNextTarget />

        <!-- Row 2, Column 1 -->
        <ProfileCategories />

        <!-- Row 2, Column 2 -->
        <ProfileMap />

        <!-- Row 2, Column 3 -->
        <ProfileCalendar />

        <!-- Row 3, Column 1 -->
        <ProfileDownload />

        <!-- Row 3, Column 2 -->
        <ProfileTimeSeries />

        <!-- Row 3, Column 3 -->
        <ProfilePhotos />
    </section>
</template>

<script>
import ProfileWelcome from '../../components/Profile/top/ProfileWelcome';
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
        ProfileWelcome,
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
        await this.$store.dispatch('GET_CURRENT_USER');

        await this.$store.dispatch('GET_USERS_PROFILE_DATA');
    }
}
</script>

<style lang="scss">

    .profile-container {
        min-height: calc(100vh - 82px);
        background-color: #341f97;
        display: grid;
        grid-template-columns: 1fr 2fr 1fr;
        grid-template-rows: 0.5fr 1fr 1fr;
        column-gap: 1em;
        row-gap: 1em;
        padding: 3em;
    }

    .profile-card {
        background-color: #292f45;
        border-radius: 6px;
        box-shadow: 0 0.5em 1em -0.125em rgb(10 10 10 / 10%), 0 0px 0 1px rgb(10 10 10 / 2%);
        color: #4a4a4a;
        display: block;
        padding: 1.25rem;
    }

    .profile-card p {
        color: white;
    }

    .profile-stat-card p {

    }
</style>
