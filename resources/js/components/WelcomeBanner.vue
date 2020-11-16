<template name="anonymous-presence">
    <div id="emaildiv" class="notification is-success" style="margin-bottom: -20px; z-index: 999; text-align: center;" v-show="userRegistered" @click="hideBanner">      
        <div>{{ $t('home.welcome.verified') }}</div>
        <button class="button is-danger mt-3">
            <span>{{ $t('home.welcome.close') }}</span>
            <span class="icon is-small">
            <a class="delete"></a>
            </span>
        </button>
    </div>
</template>

<script>

	export default {
        data() {
            return {
                userRegistered: true
            }
        },

        methods: {
         hideBanner(){
             this.userRegistered = !this.userRegistered;
         }
        }
    }
</script>
