<template name="passport">
	<div>
		<div class="columns">
			<div class="column is-half">
				<br>
				<h1>Get token</h1>
				<input class="input" v-model="email" placeholder="email" />
				<input class="input" type="password" v-model="password" placeholder="password" />
				<button class="button is-info is-large" @click="go">Get token</button>
			</div>
		</div>
	</div>
</template>

<script>

	export default {

		data() {
			return {
				email: 'seanlynch@umail.ucc.ie',
				password: 'Seanpw32!'
			};
		},

		methods: {
			go() {
				var data = {
					grant_type: 'password',
					client_id: 4,
					client_secret: 'VxeBZ4VnY2v5Kxk1RjzbBIO9bWH3j5ICJEueorRQ',
					username: this.email,
					password: this.password
				}

				axios.post('https://openlittermap.com/oauth/token', data)
				.then(response => {
					console.log(response);
				})
				.catch(error => {
					console.error(error);
				});
			}
		}
	}
</script>