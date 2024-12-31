<template>
    <div
        class="event"
        @click="$emit('click', $event)"
    >
        <div class="top-heading">
            <img
                v-if="payload.countryCode"
                :src="countryFlag(payload.countryCode)"
                :alt="payload.countryCode"
            />
            <i v-else class="fa fa-image fa-2x"/>
            <div>
                <p class="event-bold">
                    <span v-if="payload.isPickedUp">{{ $t('home.globalMap.litter-picked-up') }}</span>
                    <span v-else>{{ $t('home.globalMap.litter-uploaded') }}</span>
                </p>

                <p class="event-location">
                    <i class="city-name">{{ cityText }}</i>{{ country }}
                </p>
            </div>
            <div class="event-source">
                <i class="fa" :class="photoSource"></i>
            </div>
        </div>

        <p v-if="payload.user.name || payload.user.username">
            {{ $t('locations.cityVueMap.by') }}
            <span class="event-bold">
                {{ payload.user.name }}
                {{ payload.user.username ? ('@' + payload.user.username) : '' }}
            </span>
        </p>

        <p class="event-team" v-if="payload.teamName">
            {{ $t('common.team') }}
            <span class="event-bold">{{ payload.teamName }}</span>
        </p>
    </div>
</template>

<script>

export default {
    name: 'ImageUploaded',
    props: ['payload'],
    data () {
        return {
            dir: '/assets/icons/flags/',
        };
    },
    methods: {
        /**
         * Return location of country_flag.png
         */
        countryFlag (countryCode)
        {
            if (!countryCode) return '';

            return this.dir + countryCode.toLowerCase() + '.png';
        },
    },
    computed: {
        country() {
            return this.payload.country?.includes('error_') ? null : this.payload.country;
        },
        state() {
            return this.payload.state?.includes('error_') ? null : this.payload.state;
        },
        city() {
            return this.payload.city?.includes('error_') ? null : this.payload.city;
        },
        cityText() {
            let result = [this.city, this.state].filter((t) => t).join(', ');
            if (result && this.country) result += ', '
            return result;
        },

        /**
         * Icon to display that shows where the image was uploaded from
         */
        photoSource()
        {
            return (this.payload.photoSource === 'web')
                ? 'fa-desktop'
                : 'fa-mobile large-icon';
        }
    }
};
</script>

<style lang="scss" scoped>
.event {
    position: relative;
    border-radius: 8px;
    margin-bottom: 10px;
    padding: 8px;
    background-color: #88d267;
    cursor: pointer;

    .event-source {
        position: absolute;
        top: 8px;
        right: 8px;

        .large-icon {
            font-size: 16px;
        }
    }

    .event-bold {
        font-weight: 700;
    }

    .event-team {
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
    }

    .top-heading {
        display: flex;
        align-items: center;
        gap: 8px;

        .event-location {
            font-size: 8px;
        }

        img {
            object-fit: fill;
            border-radius: 50%;
            height: 16px;
            width: 16px;
        }
    }
}

.city-name {
    display: none;
}

@media (min-width: 768px) {
    .city-name {
        display: inline;
    }

    .event {
        .top-heading {
            .event-location {
                font-size: 10px;
            }
        }
    }
}

@media (min-width: 1024px) {
    .event {
        padding: 10px;

        .event-source {
            top: 10px;
            right: 10px;

            .large-icon {
                font-size: 24px;
                margin-right: 4px;
            }
        }

        .top-heading {
            gap: 10px;

            .event-location {
                font-size: 12px;
            }

            img {
                height: 24px;
                width: 24px;
            }
        }
    }
}
</style>
