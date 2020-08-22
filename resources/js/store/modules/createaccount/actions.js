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
            console.log('create_account', response);

            // check response

            // CHECK PLAN

            // show stripe

            // log the user in
        })
        .catch(error => {
            console.log('error.create_account', error);

            // populate errors
        });
    }
};
