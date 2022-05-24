<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
    >
        <template v-slot:image>
            <img
                v-if="countryCode"
                :src="countryFlag(countryCode)"
                width="35"
                :alt="countryCode"/>

            <i v-else class="fa fa-image"/>
        </template>
        <template v-slot:content>
            <strong>New image</strong>
            <br>
            <i class="city-name">{{ city }}, {{ state }}</i>
            <p>{{ country }}</p>
            <p v-if="user.name || user.username">
                {{ $t('locations.cityVueMap.by') }}
                <strong>
                    {{ user.name }}
                    {{ user.username ? ('@' + user.username) : '' }}
                </strong>
            </p>
            <p v-if="teamName">{{ $t('common.team') }} <strong>{{ teamName }}</strong></p>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'ImageUploaded',
    components: {GlobalMapNotification},
    props: ['user', 'countryCode', 'city', 'state', 'country', 'teamName'],
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
@media (max-width: 768px) {
    .city-name {
        display: none;
    }
}
</style>
