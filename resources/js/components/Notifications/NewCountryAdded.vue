<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="blue"
    >
        <template v-slot:image>
            <div class="-mt-2">
                <i class="fa fa-flag"/>
            </div>
        </template>
        <template v-slot:content>
            <strong>New Country</strong>
            <p>Say hello to <i>{{ country }}</i></p>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'NewCountryAdded',
    components: {GlobalMapNotification},
    props: ['country']
};
</script>

<style lang="scss" scoped>
.-mt-2 {
    margin-top: -2px;
}
</style>
