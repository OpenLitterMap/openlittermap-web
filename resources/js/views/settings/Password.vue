<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Change My Password</h1>
		<hr>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
				<div class="row">
					<!-- Change password -->
					<form method="POST" @submit.prevent="submit">

						<!-- Old Password -->
	                    <label for="oldpassword">Enter old password</label>

                        <span
                            v-if="errorExists('oldpassword')"
                            v-text="getFirstError('oldpassword')"
                            class="red"
                        />

	                    <div class="field">
	                    	<div class="control has-icons-left">
		                        <input
                                    type="password"
                                    name="oldpassword"
                                    class="input"
                                    placeholder="*********"
                                    v-model="oldpassword"
                                >
		                        <span class="icon is-small is-left">
		      						<i class="fa fa-key" />
		    					</span>
	                        </div>
	                    </div>

	                    <!-- New Password -->
						<label for="password">Enter new password</label>

                        <span
                            v-if="form.errors.has('password')"
                            v-text="form.errors.get('password')"
                            class="red"
                        />
						<div class="field">
	                        <div class="control has-icons-left">
								<input
                                    id="password"
                                    type="password"
                                    name="password"
                                    class="input"
                                    placeholder="Enter a strong password"
                                    v-model="password"
                                />
								<span class="icon is-small is-left">
		      						<i class="fa fa-key" />
		    					</span>
	                        </div>
						</div>

						<!-- Repeat Password -->
						<label for="password_confirmation">Confirm your new password</label>

                        <span
                            v-if="form.errors.has('password_confirmation')"
                            v-text="form.errors.get('password_confirmation')"
                            class="red"
                        />

						<div class="field">
							<div class="control has-icons-left">
								<input
                                    type="password"
                                    name="password_confirmation"
                                    class="input"
                                    placeholder="Repeat your strong password"
                                    v-model="password_confirmation"
                                />
								<span class="icon is-small is-left">
		      						<i class="fa fa-key" />
		    					</span>
							</div>
						</div>

<!--                        <p v-show="this.successmessage">{{ this.successmessage }}</p>-->
<!--						<p v-show="this.errormessage" style="color: red;">{{ this.errormessage }}</p>-->

						<div class="col-md-12" style="text-align: center;">
							<button :class="button" :disabled="processing">Update Password</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Password',
    data ()
    {
        return {
            processing: false,
            oldpassword: '',
            password: '',
            password_confirmation: '',
            btn: 'button is-medium is-info'
        };
    },
    computed: {

        /**
         * Add ' is-loading' when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        }
    },
    methods: {

        /**
         * Request to update the users password
         */
        async submit ()
        {
            // action="/settings/general/update"

            this.processing = true;

            await this.$store.dispatch('CHANGE_PASSWORD', {
                oldpassword: this.oldpassword,
                password: this.password,
                password_confirmation: this.password_confirmation
            });

            this.processing = false;

            // this.form.submit('patch', '/en/settings/details/password')
            //  .then(response => {
            //     // console.log(response);
            //     this.successmessage = response.message;
            //  })
            //  .catch(error => {
            //     // console.log(error);
            //     this.errormessage = response.message;
            //  });
        }
    },
}
</script>
