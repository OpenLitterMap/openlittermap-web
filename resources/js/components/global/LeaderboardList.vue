<template>
    <div class="global-leaders">

        <!-- Leaderboard Filters -->
        <LeaderboardFilters
            :locationId="locationId"
            :locationType="locationType"
        />

        <div v-for="(leader, index) in leaders" class="leader wow slideInLeft">
            <div v-if="leader.rank" class="medal">
                <img v-if="leader.rank === 1" src="/assets/icons/gold-medal-2.png" alt="Gold spot">
                <img v-if="leader.rank === 2" src="/assets/icons/silver-medal-2.png" alt="Silver spot">
                <img v-if="leader.rank === 3" src="/assets/icons/bronze-medal-2.png" alt="Bronze spot">
            </div>
            <div v-else class="medal">
                <img v-if="index === 0" src="/assets/icons/gold-medal-2.png" alt="Gold spot">
                <img v-if="index === 1" src="/assets/icons/silver-medal-2.png" alt="Silver spot">
                <img v-if="index === 2" src="/assets/icons/bronze-medal-2.png" alt="Bronze spot">
            </div>
            <div class="rank">
                <span v-if="leader.rank">{{ getPosition(leader.rank) }}</span>
                <span v-else>{{ getPosition(index + 1) }}</span>
                <div class="flag">
                    <img
                        v-show="leader.global_flag"
                        :src="getCountryFlag(leader.global_flag)"
                        :alt="leader.global_flag"
                    />
                </div>
            </div>
            <div class="details">
                <div class="name">
                    <span v-if="leader.name || leader.username">{{ leader.name }} {{ leader.username }}</span>
                    <span v-else>{{ $t('common.anonymous') }}</span>
                </div>
                <div class="team" v-if="leader.team">
                    {{ $t('common.team') }} {{ leader.team }}
                </div>
                <div v-if="leader.social" class="social-container">
                    <a v-for="(link, type) in leader.social" target="_blank" :href="link">
                        <i class="fa" :class="type === 'personal' ? 'fa-link' : `fa-${type}`"/>
                    </a>
                </div>
            </div>
            <div v-if="leader.social" class="social-container">
                <a v-for="(link, type) in leader.social" target="_blank" :href="link">
                    <i class="fa" :class="type === 'personal' ? 'fa-link' : `fa-${type}`"/>
                </a>
            </div>
            <div class="xp">
                <div class="value">{{ leader.xp }}</div>
                <div class="text">XP</div>
            </div>
        </div>
    </div>
</template>

<script>
import moment from 'moment';
import LeaderboardFilters from "../Leaderboards/LeaderboardFilters";

export default {
	name: 'LeaderboardList',
    props: [
        'leaders',
        'locationId',
        'locationType'
    ],
    components: {
        LeaderboardFilters
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
        }
    }
}
</script>

<style lang="scss" scoped>

    .global-leaders {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 1em;

        .social-container {
            display: none;
            transition: opacity 0.3s;
            flex-direction: row;
            gap: 0.3rem;
            justify-content: flex-end;
            flex-wrap: wrap;
            min-width: 140px;
            color: #3273dc;

            a {
                width: 20px;
            }
            a:hover {
                transform: scale(1.1);
                color: #3273dc;
            }
        }

        .leader {
            position: relative;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            padding: 5px 4px;
            margin-bottom: 5px;
            color: #011638;
            display: flex;
            align-items: flex-start;
            font-size: 14px;
            transition: all 0.1s;

            &:hover {
                transform: scale(1.05);
            }

            .medal {
                position: absolute;
                top: -12px;
                left: -12px;
                width: 32px;
                z-index: 10;
            }

            .rank {
                width: 48px;
                display: flex;
                flex-direction: column;
                text-align: center;
                align-items: center;

                .flag {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    width: 48px;
                    img {
                        border-radius: 50%;
                        width: 32px;
                        height: 32px;
                        object-fit: fill;
                    }
                }
            }
            .details {
                flex: 1;
                .name {
                    font-weight: 500;
                }
                .team {
                    font-size: 12px;
                }
                .social-container {
                    display: flex;
                    justify-content: flex-start;
                }
            }
            .xp {
                display: flex;
                flex-direction: column;
                padding-right: 4px;
                .value {
                    font-weight: 500;
                }
                .text {
                    text-align: center;
                }
            }
        }
    }

    @media screen and (min-width: 768px)
    {
        .global-leaders {

            .social-container {
                display: flex;
                gap: 0.5rem;
                margin: auto 16px;

                a {
                    width: 24px;
                }
            }
            .leader {
                border-radius: 8px;
                padding: 10px 8px;
                margin-bottom: 10px;
                font-size: 16px;
                align-items: center;

                .rank {
                    flex-direction: row;
                    gap: 0;
                    width: 96px;

                    span,
                    .flag {
                        width: 48px;
                    }
                }
                .details {
                    .team {
                        font-size: 14px;
                    }
                    .social-container {
                        display: none;
                    }
                }
                .xp {
                    padding-right: 0;
                    width: 100px;
                    flex-direction: row;
                    justify-content: space-evenly;
                }
            }
        }
    }
</style>
