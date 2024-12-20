<template>
	<div>
        <button class="submit button is-danger" @click="confirmDelete">{{ $t('common.delete-image') }}</button>
	</div>
</template>

<script>
export default {
    props: ['photoid'],
    methods: {
        /**
         * Todo - make this work
         */
        async confirmDelete ()
        {
            if (confirm(this.$t('tags.confirm-delete')))
            {
                await axios.post('/profile/photos/delete', {
                    photoid: this.photoid
                })
                .then(response => {
                    console.log(response);
                    if (response.status === 200)
                    {
                        window.location.href = window.location.href;
                    }
                })
                .catch(error => {
                    console.log(error);
                });
            } else {
                console.log("Not deleted");
            }
        }
    }
}
</script>
