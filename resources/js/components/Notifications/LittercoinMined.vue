<template>
    <GlobalMapNotification
        @click="$emit('click', $event)"
        color="littercoin"
    >
        <template v-slot:image>
            <div class="flex items-center h-full">
                <img src="/assets/icons/mining.png" class="ltr-icon" alt="Mining"/>
            </div>
        </template>
        <template v-slot:content>
            <div class="flex items-center h-full">
                <div>
                    <p>{{ $t('home.globalMap.littercoin-mined') }}</p>
                    <i>{{ $t('common.reason') }}: <span class="ltr-strong">{{ getLittercoinReason(payload.reason) }}</span></i>
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
    props: ['payload'],
    methods: {
        /**
         * Using the LittercoinMined event key,
         */
        getLittercoinReason (reason) {
            if (reason === 'verified-box') {
                return this.$i18n.t('home.globalMap.littercoin-100-boxes-verified');
            } else if (reason === '100-images-verified') {
                return this.$i18n.t('home.globalMap.littercoin-100-verified');
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
