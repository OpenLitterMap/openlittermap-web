<template name="AmazonS3">
	<div>
		<p>This is the new amazon s3 component</p>
		<br>
        <form enctype="multipart/form-data" @submit.prevent>
		  <input type="file" multiple @change="onFileChange" />
		  <button class="button is-info" @click="upload">Upload</button>
        </form>
	</div>
</template>

<script>
	export default {

		data() {
			return {
				image: ''
			}
		},

		methods: {

            onFileChange(e) {
                let files = e.target.files || e.dataTransfer.files;
                if (!files.length)
                    return;
                this.createImage(files[0]);
            },

            createImage(file) {
                let reader = new FileReader();
                let vm = this;
                reader.onload = (e) => {
                    vm.image = e.target.result;
                };
                reader.readAsDataURL(file);
            },

            upload(){
                axios.post('/test', {image: this.image}).then(response => {
                	console.log(response);
                });
            }
		}

	}
</script>