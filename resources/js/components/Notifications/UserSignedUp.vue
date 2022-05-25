<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="yellow"
    >
        <template v-slot:image>
            <div class="-mt-2">
                <i class="fa fa-user fa-fw" />
            </div>
        </template>
        <template v-slot:content>
            <strong>{{ $t('home.globalMap.new-user') }}</strong>
            <p>{{ $t('home.globalMap.user-signed-up') }}</p>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'UserSignedUp',
    props: ['payload'],
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

</style>
