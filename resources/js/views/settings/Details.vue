<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Change Personal Details</h1>
		<hr>
		<p>You can update any one of these at a time</p>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
				<form name="generalForm" id="generalForm" method="POST" action="/settings/details" @submit.prevent="changeName" @keydown="nameform.errors.clear($event.target.name)">
					<!-- {{ method_field('PATCH') }} -->
					<input type="hidden" name="csrf-token" :value="csrfToken">

						<label for="name">Full name</label>
						<br>
		                <span v-if="nameform.errors.has('name')" v-text="nameform.errors.get('name')" style="color:red;"></span>
						<div class="field">
							<div class="control has-icons-left">
								<input type="text" name="name" id="name" class="input" :placeholder="this.user.name" v-model="nameform.name" />
								<span class="icon is-small is-left">
  									<i class="fa fa-user"></i>
								</span>
							</div>
						</div>

						<label for="username">Unique Identifier</label>
						<br>
		                <span v-if="nameform.errors.has('username')" v-text="nameform.errors.get('username')" style="color:red;"></span>
						<div class="field">
							<div class="control has-icons-left">
								<input type="text" name="username" id="username" class="input" :placeholder="this.user.username" v-model="nameform.username" />
								<span class="icon is-small is-left">
		      						@
		    					</span>
							</div>
						</div>

						<label for="email">Email</label>
						<br>
		                <span v-if="nameform.errors.has('email')" v-text="nameform.errors.get('email')" style="color:red;"></span>
						<div class="field">
							<div class="control has-icons-left">
								<input type="email" name="email" id="email" class="input" :placeholder="this.user.email" v-model="nameform.email" />
								<span class="icon is-small is-left">
		      						<i class="fa fa-envelope"></i>
		    					</span>
							</div>
						</div>

						<label for="password">Update Profile with password</label>
						<br>
						<p v-show="this.message" style="color: red;"> {{ this.message }}</p>
		                <span v-if="nameform.errors.has('password')" v-text="nameform.errors.get('password')" style="color:red;"></span>
						<div class="field">
							<div class="control has-icons-left">
								<input type="password" name="password" id="password" class="input" placeholder="******" v-model="nameform.password" required />
								<span class="icon is-small is-left">
		      						<i class="fa fa-key"></i>
		    					</span>
							</div>
						</div>
						<button class="button is-medium is-info">Update Profile</button>
				</form>
			</div>
		</div>
	</div>
</template>

<script>
	class Errors {
		/** Create a new errors instance */ 
		constructor() {
			this.errors = {};
		}
		/**  Get the error message for a field */ 
		get(field){
			if(this.errors[field]){
				return this.errors[field][0];
			}
		}

		/** Determine if an error exists for a given field */ 
		has(field){
			return this.errors.hasOwnProperty(field);
		}

		/** Record the new errors */ 
		record(errors){
			this.errors = errors;
		}

		/** Clear one or all error fields */ 
		clear(field){
			if (field) { 
				delete this.errors[field];
				return;
			}
			// else 
			this.errors = {};
		}

		/** Determine if we have any errors */ 
		any(){
			console.log(this);
			return Object.keys(this.errors).length > 0;
		}
	}

	class Form {

		/** Create a new Form instance */ 
		constructor(data) {
			this.originalData = data;

			// create data objects on the form
			for(let field in data) {
				this[field] = data[field];
			}

			this.errors = new Errors();
		}

		/** Fetch relevant data for the form */ 
		data() {
			let data = {};
			// filter through the original data
			for (let property in this.originalData){
				data[property] = this[property];
			}
			return data;
		}

		/** Reset the form fields */ 
		reset() {
			for(let field in this.originalData){
				this[field] = '';
			}
			this.errors.clear();
		}

		/** Submit the form */ 
		submit(requestType, url) {
			// return a set up a promise
			return new Promise((resolve, reject) => {
				// submit the ajax request
				axios[requestType](url, this.data())
				  // 200 
				 .then(response => {
				 	console.log('first');
				 	// use local onSuccess method then trigger Vue method with resolve
				 	console.log(response.data); // .email | .user_id
				 	// this.onSuccess(response.data);
				 	// callback with the data
				 	resolve(response.data);
				 	console.log('third')
				 })
				  // not 200
				 .catch(error => {
				 	this.onFail(error.response.data);
				 	reject(error.response.data);
				 });
			});
		}			

		/** Handle a successful form submission */ 
		onSuccess(data){
		 	console.log('second');
			// this.reset();
		};

		/** Handle a failed form submission */ 
		onFail(errors) {
			console.log(errors);
			this.errors.record(errors);
		}
	}

	export default {
		props: ['user', 'subscription'],
		data() {
			return {
				image: '',
				message: '',
				nameform: new Form({
					name: '',
					username: '',
					email: '',
					password: '',
				}),
			};
		},
		methods: {
			changeName() {
				this.nameform.submit('patch', '/en/settings/details')
				.then(response => { 
				 	console.log(response);
				 	this.message = response.message;
				 	window.location.href = window.location.href;
				 })
				 .catch(error => {
				 	console.log(error);
				 });
			}
		},
		computed: {
			userImage() {
				return '/uploads/' + this.id +  '/avatar/' + this.avatar;
			},
			csrfToken() {
        		return OLM.csrfToken;
			}
		}
	}
</script>