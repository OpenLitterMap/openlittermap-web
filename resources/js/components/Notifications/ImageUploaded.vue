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
                <p class="event-bold" v-if="payload.isUserVerified">
                    <span v-if="payload.isPickedUp">Litter Picked Up</span>
                    <span v-else>Litter Mapped</span>
                </p>
                <p class="event-bold" v-else>New image</p>
            </div>
        </div>

        <i class="city-name">{{ payload.city }}, {{ payload.state }}</i>
        <p>{{ payload.country }}</p>

        <p v-if="payload.user.name || payload.user.username">
            {{ $t('locations.cityVueMap.by') }}
            <span class="event-bold">
                {{ payload.user.name }}
                {{ payload.user.username ? ('@' + payload.user.username) : '' }}
            </span>
        </p>

        <p v-if="payload.teamName">
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
        countryFlag (countryCode) {
            if (!countryCode) {
                return '';
            }

            return this.dir + countryCode.toLowerCase() + '.png';

        },
    }
};
</script>

<style lang="scss" scoped>
.event {
    border-radius: 8px;
    margin-bottom: 10px;
    padding: 10px;
    background-color: #88d267;
    cursor: pointer;

    .event-bold {
        font-weight: 700;
    }

    .top-heading {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 5px;

        img {
            object-fit: cover;
            border-radius: 50%;
            height: 24px;
            width: 24px;
        }
    }
}

@media (max-width: 1024px) {
    .event {
        padding: 8px;

        .top-heading {
            gap: 8px;
            margin-bottom: 2px;
        }
    }
}

@media (max-width: 768px) {
    .city-name {
        display: none;
    }
}
</style>
