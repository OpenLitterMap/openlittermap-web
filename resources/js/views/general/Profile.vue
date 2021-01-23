<template>
    <section class="profile-container">

        <!--  Todo - Show Loading -->
        <!--  Todo - Later: Add translations -->
        <!--  Todo - Later: Seperate each block into its own component -->
        <!--  Todo - Animate numbers incrementing from previous to current values -->

        <!-- Column 1, Row 1 -->
        <div class="profile-card">
            <p class="mb1">Welcome to your new Profile, {{ name }}</p>

            <p class="mb1">Out of {{ totalUsers }} users</p>

            <p>You are currently in {{ usersPosition }} place</p>
        </div>

        <!-- Column 1, Row 2 -->
        <div class="profile-card">
            <p class="mb1">Statistics</p>

            <div class="flex mb2">

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
                        <p class="profile-text">% photos</p>
                    </div>
                </div>

                <div class="profile-stat-card">
                    <img src="/assets/icons/bronze-medal.svg" />

                    <div>
                        <p class="profile-stat">{{ tagPercent }}%</p>
                        <p class="profile-text">% tags</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Column 1, Row 3 -->
        <div class="profile-card">
            <p class="mb1">Next Target / Progress</p>

            <p>You have reached <strong>Level 5</strong></p>
            <p>You need 300xp to reach the next level.</p>

            <!-- Change time period -->
            <select v-model="period" @change="changePeriod" class="input" style="float: right; width: 10em;">
                <option v-for="time in timePeriods" :value="time">{{ getPeriod(time) }}</option>
            </select>
        </div>

        <!-- Column 2, Row 1 -->
        <div class="profile-card">
            <p>Spider chart of the categories added</p>
        </div>

        <!-- Column 2, Row 2 -->
        <div class="profile-card" style="padding: 0 !important;">
            <ProfileMap />
        </div>

        <!-- Column 2, Row 3 -->
        <div class="profile-card">
            <p>Gamification / Awards</p>
        </div>

        <!-- Column 3, Row 1 -->
        <div class="profile-card">
            <p>Download My Data</p>
        </div>

        <!-- Column 3, Row 2 -->
        <div class="profile-card">
            <p>Time-series chart of the users data</p>
        </div>

        <!-- Column 3, Row 3 -->
        <div class="profile-card">
            <p>Spider chart of all categories</p>
            <p># Locaions added</p>
        </div>

    </section>
</template>

<script>
import moment from 'moment';
import ProfileMap from '../../components/Profile/ProfileMap';

export default {
    name: 'Profile',
    components: {
        ProfileMap
    },
    async created ()
    {
        await this.$store.dispatch('GET_USERS_POSITION');
    },
    data ()
    {
        return {
            period: 'today',
            timePeriods: [
                'today',
                'week',
                'month',
                'year',
                'all'
            ],
        };
    },
    computed: {

        /**
         * The users name
         */
        name ()
        {
            return this.user.user.name;
        },

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
    },
    methods: {

        /**
         * Get map data
         */
        async changePeriod ()
        {
            await this.$store.dispatch('GET_USERS_PROFILE_MAP_DATA', this.period);
        },


        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (! period) period = this.period;

            return this.$t('teams.times.' + period)
        },
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
