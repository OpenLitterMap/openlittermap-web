<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="green"
    >
        <template v-slot:image>
            <div class="-mt-2">
                <i class="fa fa-users fa-fw" />
            </div>
        </template>
        <template v-slot:content>
            <p>Cleanup created</p>
            <i>{{ $t('home.globalMap.say-hello-to') }} <strong>{{ payload.name }}</strong>!</i>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from "./GlobalMapNotification";

export default {
    name: "CleanupCreated",
    props: ['payload'],
    components: {
        GlobalMapNotification
    }
}
</script>

<style scoped>

</style>
