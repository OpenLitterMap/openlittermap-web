<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="yellow"
    >
        <template v-slot:image>
            <div class="-mt-2">
                <i class="fa fa-user user-event-icon" />
            </div>
        </template>
        <template v-slot:content>
            <strong>New User</strong>
            <p>A new user has signed up!</p>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'UserSignedUp',
    components: {
        GlobalMapNotification
    }
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
    .-mt-2 {
        margin-top: -2px;
    }

    .user-event-icon {
        font-size: 1.5em;
        padding: 7px;
    }

</style>
