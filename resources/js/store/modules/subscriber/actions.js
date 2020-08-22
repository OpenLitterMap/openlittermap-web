export const actions = {

    /**
     * A new subscriber wants to receive emails
     */
    async SUBSCRIBE (context, payload)
    {
        await axios.post('/subscribe', {
            email: payload
        })
        .then(response => {
            console.log('subscribe', response)

            // show notification
            context.commit('has_subscribed', true);

            // hide notification
            setTimeout(() => {
                context.commit('has_subscribed', false);
            }, 5000)

            // do something else
        })
        .catch(error => {
            console.log('error.subscribe', error.response.data.errors);

            context.commit('subscribeErrors', error.response.data.errors);
        });
    }

};
