<template name="anonymous-presence">
    <div id="emaildiv" class="notification is-success columns is-vcentered" style="margin-bottom: -20px; z-index: 999; text-align: center;">      
        <div class="column"></div>
        <div class="column is-three-quarters">{{ $t('home.welcome.verified') }}</div>
        <div class="column">
        <div>
            <span class="icon is-small mt-1" @click="deleteEmailSession">
                <a class="delete"></a>
            </span></div>
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
