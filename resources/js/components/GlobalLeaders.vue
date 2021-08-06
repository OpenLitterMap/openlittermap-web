<template>
	<div class="columns is-centered">
		<div class="column is-half">
			<table class="table is-fullwidth" style="background-color: transparent;">
				<thead>
					<tr>
						<th>{{ $t('location.position') }}</th>
						<th>{{ $t('location.name') }}</th>
						<th>{{ $t('location.xp') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(leader, i) in leaders" class="wow slideInLeft">
						<td style="color :white; position: relative; width: 20%;">
							{{ positions[i] }}
							<!-- if mobile -->
							<img v-show="leader.flag" :src="getCountryFlag(leader.flag)" class="leader-flag" />
						</td>

                        <!-- Todo .... trail characters after max-width reached -->
                        <!-- Todo .... number animation per user -->
						<td style="color:white;">
							{{ leader.name }}
							{{ leader.username }}
							<!-- if desktop -->
						</td>
						<td style="color:white; width: 20%;">{{ leader.xp }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script>
export default {
	name: 'GlobalLeaders',
	data ()
	{
		return {
			dir: '/assets/icons/flags/',
			positions: ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th']
		};
	},
	computed: {

		/**
		 * Top-10 leaderboard
		 */
		leaders ()
		{
			return this.$store.state.locations.globalLeaders;
		}
	},
	methods: {

		/**
		 * Show flag for a leader if they have country set
		 */
		getCountryFlag (country)
		{
			if (country)
			{
				country = country.toLowerCase();

				return this.dir + country + '.png';
			}

			return '';
		}
	}
}
</script>

<style scoped>

	.leader-flag {
		height: 1em !important;
		position: absolute;
		left: 50%;
		top: 30%;
	}

    @media screen and (max-width: 678px)
    {
        td {
            padding: 0.5em;
        }
    }
</style>
