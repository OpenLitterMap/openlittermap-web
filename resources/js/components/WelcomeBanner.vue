<template name="anonymous-presence">
    <div v-show="showEmailConfirmedBanner"  id="emaildiv" class="notification is-success" style="margin-bottom: -20px; z-index: 999; text-align: center;">
        <div class="welcome-msg">{{ $t('home.welcome.verified') }} </div>
        <div class=""><span class="icon is-small mt-1" @click="hideEmailConfirmedBanner"><a class="delete" /></span></div>  
    </div>
</template>

<script>

	export default {
        props: ['showEmailConfirmedBanner'],
        methods: {
            /**
             * Delete the welcome div when a user verifies their email address
             */
            hideEmailConfirmedBanner ()
            {
                this.showEmailConfirmedBanner = false;

            }
        }
    }
</script>

<style scoped>
 #emaildiv {
     display: flex;
     justify-content: center;
 }
 .welcome-msg {
     margin-right: 10px;
 }
</style>