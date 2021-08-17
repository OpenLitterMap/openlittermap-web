<template>
    <div class="event">
        <div class="event-image">
            <img
                v-if="countryCode"
                :src="countryFlag(countryCode)"
                width="35"
                :alt="countryCode"/>

            <i v-else class="fa fa-image"/>
        </div>
        <div class="event-content">
            <strong>New image</strong>
            <br>
            <i class="event-subtitle city-name">{{ city }}, {{ state }}</i>
            <p class="event-subtitle">{{ country }}</p>

            <p v-show="teamName">By Team: <strong>{{ teamName }}</strong></p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ImageUploaded',
    props: ['countryCode', 'city', 'state', 'country', 'teamName'],
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

<style lang="scss">

.event {
    border-radius: 8px;
    margin-bottom: 10px;
    background-color: #88d267;
    display: flex;
    cursor: pointer;
}

.event-image {
    padding: 16px;
}

.event-content {
    padding-top: 10px;
    padding-bottom: 10px;
}

@media (max-width: 1024px) {
    .event-image {
        padding: 12px;
    }
    .event-content {
        padding-top: 8px;
        padding-bottom: 8px;
    }
}

@media (max-width: 768px) {
    .city-name {
        display: none;
    }
}
</style>
