<template name="anonymous-presence">
    <div id="emaildiv" class="notification is-success" style="margin-bottom: -20px; z-index: 999; text-align: center;">
        <div class="welcome-msg">{{ $t('home.welcome.verified') }} </div>
        <div class=""> 
            <span class="icon is-small mt-1" @click="deleteEmailSession">
                <a class="delete"></a>
            </span> 
        </div>  
    </div>
</template>

<script>

	export default {
        methods: {
            /**
             * Delete the welcome div when a user verifies their email address
             */
            deleteEmailSession ()
            {
                document.getElementById('emaildiv').style.display = "none";

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