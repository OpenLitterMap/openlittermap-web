<template>
	<div>
		<div style="padding-top: 2em;">
			<h1 class="title is-4">Teams</h1>
			<hr>
			<br>
			<div class="columns">
				<div class="column is-offset-1">
					<h1 class="title is-4" id="welcome">Welcome to OpenLitterMap Teams:</h1>
					<div id="buttons">
						<br>
						<button id="createbutton" class="button is-large is-success" @click="create">Create A Team</button>
						<br>
						<br>
						<button id="joinbutton" class="button is-large is-danger" @click="join">Join A Team</button>
						<br>
						<br>
						<button v-show="this.joinedteams.length > 0" id="managebutton" class="button is-large is-dark" @click="manage">Manage My Teams</button>
					</div>

					<!-- CREATE -->
					<div id="createDiv" style="visibility: hidden;">
						<h1 class="title is-3">Create a new team:</h1>
						<!-- <p>You have not yet joined a team. Would you like to create one?</p> -->
						<br>
						<p>By creating and joining a team you can:</p>
						<ul>
							<li>- Compete against other Teams in the Team Leaderboard.</li>
							<li>- Work together to map large areas of plastic pollution.</li>
							<li>- Make a huge contribution to the OpenLitterMap database.</li>
							<li>- Split up jobs between mapping and processing.</li>
							<li>- Improve Corporate Social Responsibilty or your CV or your local business ethics and marketing!</li>
							<li>- Get improved analytical insights into your Teams behaviour! Not available to normal users.</li>
						</ul>
						<br>
						<p>This is an annual fee.</p>
						<select name="team" v-model="team">
							<option v-for="team in this.myteams" :value="team.id">
								{{ team.team }} &mdash; €{{ team.price / 100}}
							</option>
						</select>
						<br>
						<br>
						<div v-if="this.team == 1">
							<p>Create a team of Friends</p>
							<br>
							<ul>
								<li>- Maximum of 10 users per team</li>
							</ul>
						</div>
						<div v-if="this.team == 2">
							<p>Create a team for a School</p>
							<br>
							<ul>
								<li>- No limit on number of participants per Team.</li>
								<li>- If you wish to split the School into different Teams you must create another Team.</li>
								<li>- Users on this team are considered underage and are anonymised by default.</li>
								<li>- Individual users made anonymous on public maps by default.</li>
							</ul>
						</div>
						<div v-if="this.team == 3">
							<p>Create a team for a Non-Governmental Organisation</p>
							<br>
							<ul>
								<li>- No limit on number of participants</li>
							</ul>
						</div>
						<div v-if="this.team == 4">
							<p>Create a team for a small business</p>
							<ul>
								<li>- Maximum of 10 participants per team</li>
								<li>- You can create multiple teams</li>
								<li>- Improve the visibility of your business!</li>
								<li>- Sell your products through OpenLitterMap!</li>
							</ul>
						</div>
						<div v-if="this.team == 5">
							<p>Create a team for a Goverment Agency</p>
							<ul>
								<li>- No limit to the number of participants within your administrative zone</li>
								<li>- Create the data to implement and evaluate your own services and policies</li>
								<li>- Enforce new laws and taxes</li>
								<li>- Cost-effective crowdsourcing solution to monitor your responsibilites</li>
							</ul>
						</div>
						<div v-if="this.team == 6">
							<p>Create a team for a Corporation</p>
							<ul>
								<li>- Advance your CSR</li>
								<li>- Maximum of 100 participants per team</li>
								<li>- You can create multiple teams</li>
								<li>- Your teams can compete against each other for a top spot in multiple different leaderboards incl top-10 global</li>
								<li>- Corporate friendly competitive environment</li>
								<li>- Corporate statistics will be included in the OpenLitterMap Hall of Fame for history to remember (Coming soon).</li>
							</ul>
						</div>
						<br>
						<div class="columns">
							<div class="column is-half">
							
				                <!-- USERNAME OR ORGANISATION -->
			                    <label for="teamname">Team Name</label>
				              	<span class="is-danger"></span>
			                    <br>
			                    <div class="input-group">
			                        <span class="input-group-addon" id="sizing-addon2">
			                        	<i class="fa fa-users icon-block"></i>
			                        </span>
			                        <input id="teamname" name="teamname" type="text" class="form-control" placeholder="Team Awesome" aria-describedby="sizing-addon2" v-model="teamname" />
			                    </div>
			                </div>
	                    </div>
						<button class="button is-large is-primary" @click="createNewTeam">Create a team</button>
					</div>

					<!-- JOIN -->
					<div id="joinDiv" style="visibility: hidden;">
						<h1 class="title is-3">Join a Team:</h1>
						<br>
						<form @submit.prevent>
							<label for="name">Enter the Team Leaders email</label>
			                <div class="input-group">
			                    <span class="input-group-addon" id="sizing-addon2">
			                        <span class="glyphicon glyphicon-envelope"></span>
			                    </span>
			                    <input id="leadersemail" name="leadersemail" type="email" class="form-control" placeholder="teamleader@email.com" aria-describedby="sizing-addon2" v-model="tlemail" required />
			                    <br>
			                </div>
			                <div class="has-text-centered">
				                <button class="button is-large is-success" @click="joinTeam">Request to join</button>
			                </div>
						</form>
					</div>

					<!-- MANAGE -->
					<div id="manageDiv" style="visibility: hidden;">
						<h1 class="title is-3">Manage My Teams:</h1>
						<br>
						<h1 class="subtitle is-4">Currently active team: <strong style="color: green;">{{ this.active_team }}</strong></h1>
						<br>
						<p><strong>Manage My Team:</strong> Choose a Team to manage</p>
						<br>
						<select v-model="myteamname" @change="changeManagedTeam" id="manageTeamsSelect">
							<option v-for="a in this.joinedteams">{{ a["name"] }}</option>
						</select>
						<br>
						<br>
						<div v-for="myTeam in this.selectedTeams">
							<div class="tabs is-centered is-large">
								<ul id="mytabs">
									<li class="is-active" id="general-tab"><a @click="generalTab">General</a></li>
									<li id="team-tab"><a @click="teamTab">Team</a></li>
									<li id="resources-tab"><a @click="resourcesTab">Resources</a></li>
									<li id="statistics-tab"><a @click="statisticsTab">Statistics</a></li>
								</ul>
							</div>
							<hr>

							<div id="inject">
								<div id="general-content">
									<h4 class="subtitle is-4" v-if="myteamname == active_team"><strong style="color: green;">Currently active Team</strong></h4>
									<h4 class="subtitle is-4" v-else><button class="button is-medium is-success" @click="changeTeam">Activate this team?</button></h4>
									<h4 class="subtitle is-4" v-if="myTeam['TeamLeader']"><strong>Your status:</strong> Team Leader</h4>
									<h4 class="subtitle is-4" v-else><strong>Your status:</strong> Ordinary Member</h4>
									<h4 class="subtitle is-4"><strong>Type:</strong> {{ myTeam["type_name"] }}</h4>
									<h4 class="subtitle is-4"><strong>Members:</strong> {{ myTeam["members"] }}</h4>
									<br>
								</div>
							</div>

							<div id="hidden-div" style="visibility: hidden;">

								<div id="team-content">
									<h4 class="title is-4"><strong>Team</strong></h4>
									<p>My Team</p>
								</div>

								<div id="resources-content">
									<p>Images Remaining: {{ myTeam["images_remaining"] }}</p>
								</div>

								<div id="statistics-content">
									<p>Total Litter Mapped: {{ myTeam["total_litter"] }}</p>
									<p>Total Images Uploaded: {{ myTeam["total_images"] }}</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	
	import MyTeam from '../components/MyTeam.vue';
	import VeeValidate from 'vee-validate';

	export default {

		props: ['teamtypes', 'email', 'teams', 'active_team'],

		components: {
			MyTeam
		},

		computed: {
			selectedTeams() {
				return this.joinedteams.filter((myTeam) => this.myteamname == myTeam["name"]);
			}
		},

		data() {
			return {
				team: 1,
				myteams: [],
				teamname: '',
				stripeEmail: '',
				stripeToken: '',
				joinedteams: [],
				tlemail: '',
				myteamname: '',
			}
		},

		mounted() {
			this.myteams = JSON.parse(this.teamtypes);
			// this.team = this.myteams[0]["name"];

		},

		// Created first 		
		created() {
			console.log('teams here');
			console.log(this.teamtypes);
			// console.log('my active team:');
			// console
			// axios.get('/settings/teams/get', {
			// 	params: {
			// 		id: this.active_team
			// 	}
			// })

			// .then(response => {
			// 	console.log(response);
			// 	console.log(response.name);
			// })

			// .catch(error => {
			// 	console.error(error);
			// });

			// console.log('printing team name');
			// console.log(this.joinedteams);
			// console.log(this.joinedteams)[0];
			// console.log(this.joinedteams[1]);
			// this.team = this.joinedteams[0]["name"];
			// console.log(this.team);

			console.log('Parsing teams');
			var myTeams = JSON.parse(this.teams);

			for(var i=0; i<myTeams.length; i++) {
				console.log(myTeams[i]);
				this.joinedteams.push(myTeams[i]);
			}
			console.log('mounted. Teams parsed');
			console.log(this.joinedteams);
			console.log(this.joinedteams[0]);
			this.myteamname = this.joinedteams[0]["name"];
			console.log('teams parsed');
			console.log(this.myteamname);

		},

		methods: {

			create() {
				console.log('creating a new team');
				document.getElementById('buttons').outerHTML="";
				document.getElementById('createDiv').style.visibility = 'visible';
			},

			// findTeamById(id){
			// 	return this.plans.find(plan => plan.id == id);
			// },

			createNewTeam() {

				// find the current team type

				if(this.teamname.length == 0) {
					return alert('You must enter a valid team name.');
				}

				var myTeams = JSON.parse(this.teamtypes);
				for(var i=0; i<myTeams.length; i++) {
					// console.log(myTeams[i]);
					if(this.team == myTeams[i].id) {
						var team = myTeams[i];
					}
				}

				console.log(team);

				// stripe payment and subscription
				this.stripe = StripeCheckout.configure({
					key: OLM.stripeKey,
					image: "https://stripe.com/img/documentation/checkout/marketplace.png",
					locale: "auto",
					panelLabel: "Subscribe For",
					email: this.email,

					token: (token) => { // use the arrow ES15 syntax to make this local

						// input the token id into the form for submission
						this.stripeToken = token.id,
						this.stripeEmail = token.email;
						console.log(this);
						axios.post('/settings/teams/create', this.$data)

							// respond immediately 
							 .then(response => { 
							 	// this.submitting = false;
							 	// this.form.reset();
							 	console.log(response);
							 	alert('Congratulations! You have created a new team!');
							 })
							 // once alert closes, the response comes in 

							 .catch(error => {
							 	// console.log('errors');
							 	console.log(error);
							 	// this.submitting = false;
							 	// this.form.reset();
							 	this.status = 'Sorry, but your card was declined. Error!!!';
							 });
					} // end token 
				});

				// Stripe setup 2: Open the modal when 
				this.stripe.open({
					name: team.team,
					description: team.description,
					zipCode: false,
					amount: team.price
				});
			},
				
			join() {
				console.log('joining a new team');
				document.getElementById('buttons').outerHTML="";
				document.getElementById('welcome').outerHTML="";
				document.getElementById('createDiv').outerHTML="";
				document.getElementById('manageDiv').outerHTML="";
				document.getElementById('joinDiv').style.visibility = 'visible';
			},

			joinTeam() {
				console.log('request to join a new team');
				// send email to team leader 
				axios.post('/settings/teams/request', {'email': this.email, 'tlemail': this.tlemail})
					// respond immediately 
					 .then(response => { 
					 	// this.submitting = false;
					 	console.log(response);
					 	console.log(response.data);
					 	if(response.data == "Error") {
					 		return alert('Sorry, but the email you gave us is not recognized. Please try again.');
					 	}
					 	alert('Congratulations! You have successfully applied to Join a new Team. We have sent a request for the Team Leader to review your application. Please remain seated and keep your hands inside the vehicle at all times. Have fun :-)');
					 })
					 // once alert closes, the response comes in 

					 .catch(error => {
					 	// console.log('errors');
					 	console.log(error);
					 	// this.submitting = false;
					 	// this.status = 'Sorry, but your card was declined. Error!!!';
					 	alert('Sorry, but there was a problem with your request to join this team. Please double-check the email you are submitting and try again. If problems persist please contact us openlittermap@gmail.com');
					 });
			},

			manage() {
				console.log('manage My Teams');
				document.getElementById('buttons').outerHTML="";
				document.getElementById('welcome').outerHTML="";
				document.getElementById('createDiv').outerHTML="";
				document.getElementById('joinDiv').outerHTML="";
				document.getElementById('manageDiv').style.visibility = 'visible';
				console.log(this.teams);
				// var myTeams = JSON.parse(this.teams);
				// for(var i=0; i<myTeams.length; i++) {
				// 	console.log(myTeams[i]);
				// 	this.joinedteams.push(myTeams[i]);
				// }
				// this.team = this.joinedteams[0]["name"];
			},

			manangeMyTeam(myTeam) {
				console.log('manage my team');
				console.log(myTeam);
				document.getElementById('manageDiv').outerHTML="";
			},

			changeManagedTeam() {
				console.log('changing the teams');
				console.log(this.myteamname);
				// this.team = this.joinedteams[n];
				// for(var i=0; i<this.joinedteams.length; i++) {
					// console.log(this.joinedteams[i]);
				// }
				var selectDiv = document.getElementById('manageTeamsSelect');
				console.log(selectDiv);
			},

			generalTab() {
				console.log('general tab');
				var generalTab = document.getElementById('general-tab');
				var teamTab = document.getElementById('team-tab');
				var resourcesTab = document.getElementById('resources-tab');
				var statisticsTab = document.getElementById('statistics-tab');

				var generalContent = document.getElementById('general-content');
				var teamContent = document.getElementById('team-content');
				var resourcesContent = document.getElementById('resources-content');
				var statisticsContent = document.getElementById('statistics-content');

				var inject = document.getElementById('inject');
				var hidden = document.getElementById('hidden-div');

				hidden.appendChild(resourcesContent);
				hidden.appendChild(statisticsContent);
				hidden.appendChild(teamContent);
				inject.appendChild(generalContent);

				resourcesTab.classList.remove('is-active');
				statisticsTab.classList.remove('is-active');
				teamTab.classList.remove('is-active');
				generalTab.classList.add('is-active');

			},

			teamTab() {
				console.log('team tab');
				var generalTab = document.getElementById('general-tab');
				var teamTab = document.getElementById('team-tab');
				var resourcesTab = document.getElementById('resources-tab');
				var statisticsTab = document.getElementById('statistics-tab');

				var generalContent = document.getElementById('general-content');
				var teamContent = document.getElementById('team-content');
				var resourcesContent = document.getElementById('resources-content');
				var statisticsContent = document.getElementById('statistics-content');

				var inject = document.getElementById('inject');
				var hidden = document.getElementById('hidden-div');

				hidden.appendChild(generalContent);
				hidden.appendChild(statisticsContent);
				hidden.appendChild(resourcesContent);
				inject.appendChild(teamContent);

				resourcesTab.classList.remove('is-active');
				statisticsTab.classList.remove('is-active');
				generalTab.classList.remove('is-active');
				teamTab.classList.add('is-active');
			},

			resourcesTab() {
				console.log('resources');
				var general = document.getElementById('general-tab');
				var teamTab = document.getElementById('team-tab');
				var resources = document.getElementById('resources-tab');
				var statistics = document.getElementById('statistics-tab');

				var generalContent = document.getElementById('general-content');
				var teamContent = document.getElementById('team-content');
				var resourcesContent = document.getElementById('resources-content');
				var statisticsContent = document.getElementById('statistics-content');

				var inject = document.getElementById('inject');
				var hidden = document.getElementById('hidden-div');

				hidden.appendChild(generalContent);
				hidden.appendChild(statisticsContent);
				hidden.appendChild(teamContent);
				inject.appendChild(resourcesContent);

				general.classList.remove('is-active');
				statistics.classList.remove('is-active');
				teamTab.classList.remove('is-active');
				resources.classList.add('is-active');
			},

			statisticsTab() {
				console.log('statistics');
				var general = document.getElementById('general-tab');
				var teamTab = document.getElementById('team-tab');
				var resources = document.getElementById('resources-tab');
				var statistics = document.getElementById('statistics-tab');

				var generalContent = document.getElementById('general-content');
				var teamContent = document.getElementById('team-content');
				var resourcesContent = document.getElementById('resources-content');
				var statisticsContent = document.getElementById('statistics-content');

				var inject = document.getElementById('inject');
				var hidden = document.getElementById('hidden-div');

				hidden.appendChild(generalContent);
				hidden.appendChild(resourcesContent);
				hidden.appendChild(teamContent);
				inject.appendChild(statisticsContent);

				general.classList.remove('is-active');
				resources.classList.remove('is-active');
				teamTab.classList.remove('is-active');
				statistics.classList.add('is-active');
			},

			changeTeam() {
				console.log('change team');
				axios({
					method: 'POST',
					url: '/settings/teams/change',
					data: {
						newteam: this.myteamname,
					}
				})
				.then(response => {
					console.log(response);
					if(response.data.message == 'Success') {
						alert('Congratulations! You have changed your active team');
						window.location.href = window.location.href;
					}
				})
				.catch(error => {
					console.log(error);
					alert('Sorry, there was an error changing your team');
				});
			}

			// join() {
			// 	axios({
			// 		method: 'POST',
			// 		url: '/settings/privacy/update',
			// 		data: {
			// 			name: this.vname,
			// 			username: this.vusername
			// 		}
			// 	})
			// 	.then(response => {
			// 		console.log(response);
			// 		this.msgname = response.data.message.name;
			// 		this.msgusername = response.data.message.username;
			// 		window.location.href = window.location.href;
			// 	})
			// 	.catch(error => {
			// 		console.log(error);
			// 	});
			// }
		}
	}
</script>