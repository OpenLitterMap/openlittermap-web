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

            // do something else
        })
        .catch(error => {
            console.log('error.subscribe', error);
        });
    }

};
