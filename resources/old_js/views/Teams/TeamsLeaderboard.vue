<template>
    <section>
        <div class="my-teams-container">
            <h1 class="title is-2">{{ $t('teams.leaderboard.title') }}</h1>

            <p v-if="loading">{{ $t('common.loading') }}</p>

            <table v-else class="table is-fullwidth is-hoverable has-text-centered">
                <thead>
                    <th>{{ $t('teams.leaderboard.position-header') }}</th>
                    <th>{{ $t('teams.leaderboard.name-header') }}</th>
                    <th>{{ $t('teams.leaderboard.litter-header') }}</th>
                    <th>{{ $t('teams.leaderboard.photos-header') }}</th>
                    <!-- Total members -->
                    <!-- Last Upload -->
                    <th>{{ $t('teams.leaderboard.created-at-header') }}</th>
                </thead>

                <tbody>
                    <tr v-for="(team, index) in teams">
                        <td>
                            <div class="medal-container">
                                <img
                                    v-show="index < 3"
                                    :src="medal(index)"
                                    class="medal"
                                />
                                <span>{{ index + 1 }}</span>
                            </div>
                        </td>
                        <td>{{ team.name }}</td>
                        <td>{{ team.total_litter }}</td>
                        <td>{{ team.total_images }}</td>
                        <!-- Total members -->
                        <!-- Last Upload -->
                        <td>{{ getDate(team.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script>
import moment from 'moment'

export default {
    name: "TeamsLeaderboard",
    data () {
        return {
            loading: true
        };
    },
    async created ()
    {
        await this.$store.dispatch('GET_TEAMS_LEADERBOARD');

        this.loading = false;
    },
    computed: {

        /**
         * Array of teams in the leaderboard
         */
        teams ()
        {
            return this.$store.state.teams.leaderboard;
        }
    },
    methods: {

        /**
         *
         */
        getDate (date)
        {
            return moment(date).format('LL');
        },

        /**
         * Return medal for 1st, 2nd and 3rd
         */
        medal (i)
        {
            if (i === 0) return '/assets/icons/gold-medal.png';
            if (i === 1) return '/assets/icons/silver-medal.png';
            if (i === 2) return '/assets/icons/bronze-medal.svg';

            return '';
        }
    }
}
</script>

<style scoped>

    .medal-container {
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative
    }

    .medal {
        height: 1em;
        position: absolute;
        left: 2em;
    }

</style>
