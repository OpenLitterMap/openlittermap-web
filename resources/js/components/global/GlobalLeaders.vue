<template>
	<div class="columns is-centered">
		<div class="column is-two-thirds">
			<table class="table is-fullwidth" style="background-color: transparent;">
				<thead>
					<tr>
						<th class="has-text-centered">{{ $t('location.position') }}</th>
						<th>{{ $t('location.name') }}</th>
						<th class="has-text-centered">{{ $t('location.xp') }}</th>
                        <th class="has-text-centered">Socials</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(leader, index) in leaders" class="wow slideInLeft">
						<td style="color: white;" class="has-text-centered">
                            <span v-if="leader.rank">{{ getPosition(leader.rank) }}</span>
                            <span v-else>{{ getPosition(index + 1) }}</span>
							<!-- if mobile -->
							<img
                                v-show="leader.global_flag"
                                :src="getCountryFlag(leader.global_flag)"
                                class="leader-flag"
                                :alt="leader.global_flag"
                            />
						</td>

                        <!-- Todo .... trail characters after max-width reached -->
                        <!-- Todo .... number animation per user -->
                        <td>
                            <div class="leader-name">
                                <span v-if="leader.name || leader.username">{{ leader.name }} {{ leader.username }}</span>
                                <span v-else>{{ $t('common.anonymous') }}</span>
                            </div>
                        </td>
						<td style="color:white; width: 20%;" class="has-text-centered">
                            {{ leader.xp }}
                        </td>

                        <td>
                            <span v-if="leader.social" class="social-container">
                                <a v-for="(link, type) in leader.social" target="_blank" :href="link">
                                    <i class="fa" :class="type === 'personal' ? 'fa-link' : `fa-${type}`" />
                                </a>
                            </span>
                        </td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script>
import moment from 'moment';

export default {
	name: 'GlobalLeaders',
    props: ['leaders'],
	methods: {
		/**
		 * Show flag for a leader if they have country set
		 */
		getCountryFlag (country)
		{
			if (country)
			{
				country = country.toLowerCase();

				return '/assets/icons/flags/' + country + '.png';
			}

			return '';
		},

        /**
         * Only simple way I know how to get the ordinal number in javascript
         */
        getPosition (rank) {
            // 1st, 2nd, 3rd
            return moment.localeData().ordinal(rank);
        }
    }
}
</script>

<style scoped>

    .leaderboard-heading {
        text-align: center !important;
    }

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
        height: 32px;
        margin: auto 0;
        background-color: transparent;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 4px 8px;
        z-index: 30;
        transition: opacity 0.3s;
        display: flex;
        flex-direction: row;
        gap: 0.5rem;
        justify-content: center;
    }

    /*tr:hover .social-container {*/
    /*    visibility: visible;*/
    /*    opacity: 0.8;*/
    /*}*/

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
