<template>
    <div>
        <LeaderboardFilters />

        <div class="columns">
            <div class="column is-three-quarters is-offset-1">
                <table class="table is-fullwidth" style="background-color: transparent;">
                    <tr>
                        <th>Position</th>
                        <th>Name</th>
                        <th>XP</th>
                    </tr>
                        <tr v-for="(leader, i) in leaderboard" class="wow slideInLeft">
                            <td>{{ positions[i] }}</td>
                            <td v-if="leader.name || leader.username">{{ leader.name }} {{ leader.username }}</td>
                            <td v-else>{{ $t('common.anonymous') }}</td>
                            <td>{{ leader.xp }}</td>
                        </tr>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import LeaderboardFilters from "../../../Leaderboards/LeaderboardFilters";

export default {
	name: 'Leaderboard',
    props: [
        'locationType',
        'locationId'
    ],
    components: {
        LeaderboardFilters
    },
    data () {
		return {
            positions: ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th']
        };
	},
    computed: {
        /**
         * Array of leaders
         */
        leaderboard () {
            return [];
        }
    }
}
</script>
