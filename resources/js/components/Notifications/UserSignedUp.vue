<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="yellow"
    >
        <template v-slot:image>
            <div class="flex items-center h-full">
                <i class="fa fa-user"/>
            </div>
        </template>
        <template v-slot:content>
            <div class="flex items-center h-full">
                <div>A new user has signed up!</div>
            </div>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'UserSignedUp',
    components: {GlobalMapNotification}
};
</script>

<style lang="scss" scoped>
.flex {
    display: flex;
}

.items-center {
    align-items: center;
}

.h-full {
    height: 100%;
}
</style>
