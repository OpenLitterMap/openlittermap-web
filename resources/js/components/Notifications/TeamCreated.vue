<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="colourful"
    >
        <template v-slot:image>
            <div class="-mt-2">
                <i class="fa fa-users fa-fw"/>
            </div>
        </template>
        <template v-slot:content>
            <p>{{ $t('home.globalMap.team-created') }}</p>
            <i>{{ $t('home.globalMap.say-hello-to') }} <strong>{{ payload.teamName }}</strong>!</i>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'TeamCreated',
    components: {GlobalMapNotification},
    props: ['payload'],
};
</script>

<style lang="scss" scoped>
.-mt-2 {
    margin-top: -2px;
}
</style>
