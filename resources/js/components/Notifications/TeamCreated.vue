<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="colourful"
    >
        <template v-slot:image>
            <div class="-mt-2">
                <i class="fa fa-users"/>
            </div>
        </template>
        <template v-slot:content>
            <p>A new Team has been created!</p>
            <i>Say hello to <strong>{{ teamName }}</strong>!</i>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'TeamCreated',
    components: {GlobalMapNotification},
    props: ['teamName']
};
</script>

<style lang="scss" scoped>
.-mt-2 {
    margin-top: -2px;
}
</style>
