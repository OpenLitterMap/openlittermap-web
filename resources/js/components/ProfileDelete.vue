<template>
	<div class="expand-mobile">
        <strong>Delete the image?</strong>
        <br>
        <button class="submit" @click="confirmDelete">Delete</button>
	</div>
</template>

<script>
	export default {
		props: ['photoid'],
		methods: {
			confirmDelete() {
				// todo, confirm the user wants to delete the resource.
				// alert("Sorry, this is not working yet. If you think open data on plastic pollution is important please help with the crowdfunding so we can hire a developer?");
				if (confirm("Do you want to delete this image? This cannot be undone.")) {
					axios.post('/en/profile/photos/delete', {
						photoid: this.photoid
					})
					.then(response => {
						console.log(response);
						if (response.status == 200) {
							// console.log(response.status)
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