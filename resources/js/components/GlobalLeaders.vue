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
                        <td>
                            <div class="leader-name">
                                <span v-if="leader.name || leader.username">{{ leader.name }} {{ leader.username }}</span>
                                <span v-else>{{ $t('common.anonymous') }}</span>
                            </div>
                            <span v-if="leader.social" class="social-container">
                                <a v-for="(link, type) in leader.social" target="_blank" :href="link">
                                    <i class="fa" :class="type === 'personal' ? 'fa-link' : `fa-${type}`"></i>
                                </a>
                            </span>
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

    .leader-name {
        color: white;
    }

    .social-container {
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        height: 32px;
        margin: auto 0;
        visibility: hidden;
        background-color: black;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 4px 8px;
        z-index: 30;
        opacity: 0;
        transition: opacity 0.3s;
        display: flex;
        flex-direction: row;
        gap: 0.5rem;
    }

    tr:hover .social-container {
        visibility: visible;
        opacity: 0.8;
    }

    .social-container a {
        width: 1.5rem;
    }

    .social-container a:hover {
        transform: scale(1.1)
    }

    td {
        position: relative;
    }

    @media screen and (max-width: 678px)
    {
        td {
            padding: 0.5em;
        }
    }
</style>
