<template>
	<div class="expand-mobile">
        <strong>{{ $t('common.delete-image') }}</strong>
        <br>
        <button class="submit" @click="confirmDelete">{{ $t('common.delete') }}</button>
	</div>
</template>

<script>
export default {
    props: ['photoid'],
    methods: {
        /**
         * Todo - make this work
         */
        confirmDelete ()
        {
            alert("Sorry, this is not working yet.");
            // if (confirm("Do you want to delete this image? This cannot be undone."))
            // {
            //     await axios.post('/en/profile/photos/delete', {
            //         photoid: this.photoid
            //     })
            //     .then(response => {
            //         console.log(response);
            //         if (response.status === 200)
            //         {
            //             window.location.href = window.location.href;
            //         }
            //     })
            //     .catch(error => {
            //         console.log(error);
            //     });
            // } else {
            //     console.log("Not deleted");
            // }
        }
    }
}
</script>
