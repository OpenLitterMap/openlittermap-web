<template>
	<div>
		<section class="hero is-fullheight is-primary is-bold">

			<section class="hero is-primary is-medium">
				<div class="hero-body">
					<div class="container">
						<div class="columns">
							<!-- Profile Picture -->
							<div class="column is-half">
								<img :src="this.user.profile_photo" style="max-height: 150px; border-radius: 50%;">
								<button class="button" @click="uploadProfilePhoto">Upload profile photo</button>

								<p>{{ this.user.name }}</p>
								<p>{{ this.user.username }}</p>
							</div>
							<!-- Stats -->
							<div class="column is-half">
								<div class="card">
									<p>{{ this.user.total_images }} images uploaded</p>
								</div>
								<div class="card">
									<p>{{ this.user.total_litter }} litter uploaded</p>
								</div>
								<div class="card">
									<p>0 locations added</p>
								</div>
							</div>
						</div>

						<!-- Awards -->
						<div style="display: flex;">
							<p v-for="award in awards">{{ award.title }}</p>
						</div>

						<hr>

						<div class="columns">
			                <div class="column is-three-quarters is-offset-1">
			                    <!-- XP bar variables -->
			                    <div class="columns has-text-centered">
			                        <div class="column is-one-third">
			                          <h4>Level: {{ this.user.level }} </h4>
			                        </div>
			                        <div class="column is-one-third">
			                          <h4>Current XP: {{ this.user.xp }}</h4>
			                        </div>
			                        <div class="column">
			                          <h4>XP to next level: {{ this.xpNeeded }}</h4>
			                        </div>
			                    </div>
			                </div>
			            </div>

	                   	<progress-bar
	                        :level="this.user.level" 
	                        :xp="this.user.xp" 
	                        :xpneeded="this.xpNeeded" 
	                        :startingxp="this.startingXp"
	                        @percent="setPercent"
	                    ></progress-bar>
	                    <p style="text-align: center;">{{ this.progressPercent }}%</p>

					</div>
				</div>
			</section>

			<!-- Chart -->

			<!-- Time Series -->


		</section>
	</div>
</template>

<script>

	import ProgressBar from '../components/ProgressBar.vue';

	export default {
		name: "UsersProfile",
		props: ['user', 'xpNeeded', 'startingXp'],
		created() {
			console.log("UsersProfile created");
		},
		components: {
			ProgressBar
		},
		data() {
			return {
				// percentage progress bar completed 
        		progressPercent: 0,
        		// todo - get this async.
				awards: [
					{
						title: "Smoking Uploaded",
						achieved: null,
						image: null
					},
					{
						title: "Food Uploaded",
						achieved: null,
						image: null
					},
					{
						title: "Coffee Uploaded",
						achieved: null,
						image: null
					},
					{
						title: "SoftDrinks Uploaded",
						achieved: null,
						image: null
					},
				]
			};
		},
		methods: {
			setPercent(e) {
				this.progressPercent = e;
			},
			uploadProfilePhoto() {
				axios.post('/profile/upload-profile-photo', {
					data: ''
				})
				.then(response => {
					console.log(response);
				})
				.catch(error => {
					console.log(error);
				});
			}
		}
	}
</script>