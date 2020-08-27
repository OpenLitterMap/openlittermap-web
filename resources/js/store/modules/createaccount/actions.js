export const actions = {

    /**
     * Someone wants to create a new account!
     */
    async CREATE_ACCOUNT (context, payload)
    {
        await axios.post('/register', {
            name: payload.name,
            username: payload.username,
            email: payload.email,
            password: payload.password,
            password_confirmation: payload.password_confirmation,
            g_recaptcha_response: payload.recaptcha
        })
        .then(response => {
            console.log('create_account', response); // user_id, email

            // check response

            // CHECK PLAN
            if (payload.plan == 1)
            {
                alert('Congratulations! Your free account has been created. Please verify your email to activate login');
                // login
                // reload page
            }

            // show stripe

            // log the user in
        })
        .catch(error => {
            console.log('error.create_account', error);

            // populate errors
            context.commit('createAccountErrors', error.response.data.errors);
        });
    },

    /**
     * Get all of the available plans
     */
    async GET_PLANS (context)
    {
        await axios.get(window.location.origin + '/plans')
            .then(response => {
                console.log('get_plans', response);

                context.commit('setPlans', response.data);
            })
            .catch(error => {
                console.log('error.get_plans', error);
            });
    }

};
