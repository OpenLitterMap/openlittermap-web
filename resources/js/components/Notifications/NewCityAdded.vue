<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="blue"
    >
        <template v-slot:image>
            <div class="-mt-2">
                <i class="fa fa-flag fa-fw"/>
            </div>
        </template>
        <template v-slot:content>
            <strong>{{ $t('home.globalMap.new-city') }}</strong>
            <p>{{ $t('home.globalMap.say-hello-to') }} <i>{{ payload.city }}</i></p>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'NewCityAdded',
    components: {GlobalMapNotification},
    props: ['payload'],
};
</script>

<style lang="scss" scoped>
.-mt-2 {
    margin-top: -2px;
}
</style>
