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
					<tr v-for="(leader, index) in leaders" class="wow slideInLeft">
						<td style="color :white; position: relative; width: 20%;">

                            <span v-if="leader.rank">{{ getPosition(leader.rank) }}</span>
                            <span v-else>{{ getPosition(index + 1) }}</span>
							<!-- if mobile -->
							<img
                                v-show="leader.global_flag"
                                :src="getCountryFlag(leader.global_flag)"
                                class="leader-flag"
                            />
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
                                    <i class="fa" :class="type === 'personal' ? 'fa-link' : `fa-${type}`" />
                                </a>
                            </span>
                        </td>
						<td style="color:white; width: 20%;">
                            {{ leader.xp_redis }}
                        </td>
					</tr>
				</tbody>
			</table>

            <!-- Pagination Buttons -->
            <div
                v-if="this.showPagination === true && leaderboard.users !== null"
                class="flex jc"
            >
                <button
                    v-show="currentPage > 0"
                    class="button is-medium mr-1"
                    @click="loadPreviousPage"
                >Previous</button>

                <button
                    v-show="leaderboard.users.length === 100"
                    class="button is-medium"
                    @click="loadNextPage"
                >Next</button>
            </div>
		</div>
	</div>
</template>

<script>
import moment from 'moment';

export default {
	name: 'GlobalLeaders',
    props: [
        'leaders',
        'showPagination'
    ],
    computed: {
        /**
         * Current paginated leaderboard index
         *
         * Default = 0
         */
        currentPage () {
            return this.$store.state.leaderboard.currentPage;
        },

        /**
         * Shortcut to leaderboard state
         */
        leaderboard () {
            return this.$store.state.leaderboard;
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
        },

        async loadPreviousPage () {
            await this.$store.dispatch('GET_PREVIOUS_LEADERBOARD_PAGE');
        },

        async loadNextPage () {
            await this.$store.dispatch('GET_NEXT_LEADERBOARD_PAGE');
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
