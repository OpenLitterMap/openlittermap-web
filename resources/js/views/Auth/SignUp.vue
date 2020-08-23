<template name="sign-up">
	<section class="section hero fullheight is-primary is-bold">
		<div class="container">

			<h1 class="title">Ready to join the geospatial revolution?</h1>
			<h2 class="subtitle">If you like our work, OpenLitterMap could really use your help.</h2>

			<div class="columns">
				<div class="column wow bounceInRight">
					<div class="box" id="first">
						<p class="card-header-title" style="color: #3273dc; margin-bottom: -20px;">
						  FREE
						</p>
						<div class="content">
						  <ul>
							<li>Upload 1000 images per day</li>
							<li>Unlock Badges + Rewards</li>
							<li>Earn Littercoin</li>
							<li>Compete in multiple different leaderboards</li>
						  </ul>
						</div>
						<div style="text-align: center;">
							<router-link :to="'/join'">
								<button class="button is-primary is-center" style="margin-top: -10px;">I'm in</button>
							</router-link>
						</div>
					</div>
				</div>

				<div class="column wow bounceInDown">
					<div class="box">
						<p class="card-header-title" style="color: orange;">
						  STARTUP <strong style="padding-left: 30px;">€5 per month</strong>
						</p>
						<div class="content has-text-centered">
						  <ul>
							<li>Finance the development of OpenLitterMap.</li>
							<li>Help us cover our costs.</li>
							<li>Sit back and enjoy the updates.</li>
						  </ul>
						</div>
					  	<div style="text-align: center;">
							<router-link :to="'/join?plan=startup'">
								<button class="button is-warning">I'll support!</button>
							</router-link>
				   		</div>
					</div>
				</div>

				<div class="column wow bounceInLeft">
					<div class="box">
						<p class="card-header-title" style="color: green;">
						  BASIC <strong style="padding-left: 30px;">€9.99 per month</strong>
						</p>
						<div class="content">
						  <ul>
							<li>Finance the development of OpenLitterMap.</li>
							<li>Help us cover our costs.</li>
							<li>Sit back and enjoy the updates.</li>
						  </ul>
						</div>
					  <div style="text-align: center;">
						  <router-link :to="'/join?plan=basic'">
							  <button class="button is-success">I'll support!</button>
						  </router-link>
					   </div>
					</div>
				</div>
			</div>

			<div class="columns">

				<div class="column is-4 is-offset-2 wow bounceInLeft">
					<div class="box">

						<p class="card-header-title">
						  ADVANCED <strong style="padding-left: 12px;">€20 per month</strong>
						</p>
						<div class="content">
						  <ul>
							<li>Finance the development of OpenLitterMap.</li>
							<li>Help us cover our costs.</li>
							<li>Sit back and enjoy the updates.</li>
						  </ul>
						</div>
						<div style="text-align: center;">
							<router-link :to="'/join?plan=advanced'">
								<button class="button is-dark">I'll support!</button>
							</router-link>
						</div>

					</div>
				</div>

				<div class="column is-4 wow bounceInRight">
					<div class="box">
						<p class="card-header-title" style="color: red;">
						  PRO <strong style="padding-left: 45px;">€30 per month</strong>
						</p>
						<div class="content">
							<ul>
								<li>Finance the development of OpenLitterMap.</li>
								<li>Help us cover our costs.</li>
								<li>Sit back and enjoy the updates.</li>
							</ul>
						</div>
						<div style="text-align: center;">
							<router-link :to="'/join?plan=pro'">
								<button class="button is-danger">I mean serious business.</button>
							</router-link>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</template>

<style scoped>

    .box {
        min-height: 100%;
    }

</style>
