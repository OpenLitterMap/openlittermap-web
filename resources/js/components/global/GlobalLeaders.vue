<template>
    <div class="global-leaders">
        <table class="table is-fullwidth" style="background-color: transparent;">
            <thead>
            <tr>
                <th class="has-text-centered" style="width: 15%;">{{ $t('location.position') }}</th>
                <th>{{ $t('location.name') }}</th>
                <th class="has-text-centered">{{ $t('location.xp') }}</th>
                <th class="has-text-centered hide-mobile">{{ $t('location.social') }}</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(leader, index) in leaders" class="wow slideInLeft">
                <td class="position-container">
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

                    <div class="leader-team" v-if="leader.team">
                        {{ $t('common.team') }} {{ leader.team }}
                    </div>

                    <div class="hide-desktop">
                        <span v-if="leader.social" class="social-container">
                            <a v-for="(link, type) in leader.social" target="_blank" :href="link">
                                <i class="fa" :class="type === 'personal' ? 'fa-link' : `fa-${type}`"/>
                            </a>
                        </span>
                    </div>
                </td>
                <td style="color:white; width: 20%;" class="has-text-centered">
                    {{ leader.xp }}
                </td>

                <td class="hide-mobile">
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

<style lang="scss" scoped>

    .global-leaders {
        max-width: 1000px;
        margin: 0 auto;
    }

	.leader-flag {
		height: 1em !important;
        margin-left: 1em;
	}

    .leader-team {
        color: white;
    }

    .leader-name {
        color: white;
        display: flex;
        flex-direction: row;
        gap: 4px;

        span {
            flex-shrink: 0;
        }
    }

    .social-container {
        margin: auto 0;
        color: #fff;
        transition: opacity 0.3s;
        display: flex;
        flex-direction: row;
        gap: 0.5rem;

        a {
            width: 1.5rem;
        }

        a:hover {
            transform: scale(1.1)
        }
    }

    td {
        position: relative;
        vertical-align: middle;
    }

    .position-container {
        color: white;
        padding-left: 2.5em;
    }

    .hide-desktop {
        display: none;
    }

    @media screen and (max-width: 678px)
    {
        td {
            padding: 0.5em;
        }

        .hide-mobile {
            display: none;
        }

        .hide-desktop {
            display: block;
        }

        .leader-flag {
            right: 1em !important;
        }

        .position-container {
            text-align: left;
            padding-left: 0
        }

        .social-container {
            flex-wrap: wrap;
        }
    }

    @media screen and (max-width: 1023px)
    {
        .leader-name {
            flex-direction: column;
            gap: 0;

            span {
                flex-shrink: 1;
            }
        }
    }
</style>
