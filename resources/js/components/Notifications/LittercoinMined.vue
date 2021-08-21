<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="yellow"
    >
        <template v-slot:image>
            <div class="flex items-center h-full">
                <img src="/assets/icons/mining.png" class="ltr-icon"/>
            </div>
        </template>
        <template v-slot:content>
            <div class="flex items-center h-full">
                <div>
                    <p>A Littercoin has been mined!</p>
                    <i>Reason: <span class="ltr-strong">{{ getLittercoinReason(reason) }}</span></i>
                </div>
            </div>
        </template>
    </GlobalMapNotification>
</template>

<script>
import GlobalMapNotification from './GlobalMapNotification';

export default {
    name: 'LittercoinMined',
    components: {GlobalMapNotification},
    props: ['reason'],
    methods: {
        /**
         * Using the LittercoinMined event key,
         *
         * Todo - return translated string
         */
        getLittercoinReason (reason) {
            if (reason === 'verified-box') {
                return '100 OpenLitterAI boxes verified';
            } else if (reason === '100-images-verified') {
                return '100 images verified';
            }
        }
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

.ltr-icon {
    max-width: 32px;
}

.ltr-strong {
    font-weight: 600;
}
</style>
