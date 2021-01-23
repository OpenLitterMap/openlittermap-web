<template>
    <section class="profile-container">

        <!--  Todo - Show Loading -->
        <!--  Todo - Later: Add translations -->
        <!--  Todo - Animate numbers incrementing from previous to current values -->

        <!-- Column 1, Row 1 -->
        <ProfileWelcome />

        <!-- Column 1, Row 2 -->
        <ProfileStats />

        <!-- Column 1, Row 3 -->
        <ProfileNextTarget />

        <!-- Column 2, Row 1 -->
        <ProfileCategories />

        <!-- Column 2, Row 2 -->
        <ProfileMap />

        <!-- Column 2, Row 3 -->
        <ProfileAwards />

        <!-- Column 3, Row 1 -->
        <ProfileDownload />

        <!-- Column 3, Row 2 -->
        <ProfileTimeSeries />

        <!-- Column 3, Row 3 -->
        <div class="profile-card">
            <p>Coming soon - Locations added</p>
        </div>
    </section>
</template>

<script>
import ProfileWelcome from '../../components/Profile/top/ProfileWelcome';
import ProfileStats from '../../components/Profile/top/ProfileStats';
import ProfileNextTarget from '../../components/Profile/top/ProfileNextTarget';
import ProfileCategories from '../../components/Profile/middle/ProfileCategories';
import ProfileMap from '../../components/Profile/middle/ProfileMap';
import ProfileAwards from '../../components/Profile/middle/ProfileAwards';
import ProfileDownload from '../../components/Profile/bottom/ProfileDownload';
import ProfileTimeSeries from '../../components/Profile/bottom/ProfileTimeSeries';

export default {
    name: 'Profile',
    components: {
        ProfileWelcome,
        ProfileTimeSeries,
        ProfileStats,
        ProfileNextTarget,
        ProfileCategories,
        ProfileMap,
        ProfileAwards,
        ProfileDownload
    },
    async created ()
    {
        await this.$store.dispatch('GET_USERS_POSITION');
    }
}
</script>

<style scoped>

    .profile-container {
        min-height: calc(100vh - 82px);
        background-color: #341f97;
        display: grid;
        grid-template-columns: 1fr 2fr 1fr;
        grid-template-rows: 1fr 2fr 1fr;
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

    .profile-stat-card {
        flex: 1;
        display: flex;
    }

    .profile-stat-card img {
        height: 3em;
        margin: auto 1em auto 0;
    }

    .profile-stat-card p {

    }

    .profile-stat {
        font-size: 1.5em;
        font-weight: 600;
    }

    .profile-text {
        color: #8e7fd6 !important;
    }
</style>
