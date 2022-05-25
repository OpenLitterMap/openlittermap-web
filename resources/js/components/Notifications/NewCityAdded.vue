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
            <strong>New City</strong>
            <p>Say hello to <i>{{ payload.city }}</i></p>
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
