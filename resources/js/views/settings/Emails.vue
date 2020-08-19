<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Toggle Email Subscription</h1>
		<hr>
		<p>Occasionally, we send out emails with updates and good news.</p>
		<p>You can subscribe or unsubscribe to our emails here.</p>
		<br>
		<p><b>Current Status:</b></p><p><b :style="color">{{ this.computedPresence }}</b></p>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
				<div class="row">
					<button class="button is-info" @click="toggle">Toggle Email Subscription</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	export default {
		props: ['user'],
		methods: {
			async toggle() {
				await axios.post('/en/settings/email/toggle')
				.then(response => {
					// console.log(response);
					if (response.data.sub) {
						alert("You are re-subscribed to the updates and good news. Welcome back!");
					} else {
						alert("You have unsubscribed. You will no longer recieve the good news!");
					}
					window.location.href = window.location.href;
				})
				.catch(error => {
					console.log(error);
				})
			}
		},
		computed: {
			color() {
				return this.user.emailsub == 1 ? "color: green" : "color: red";
			},
			computedPresence() {
				return this.user.emailsub == 1 ? "Subscribed" : "Unsubscribed";
			}
		}
	}
</script>