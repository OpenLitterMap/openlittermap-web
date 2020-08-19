export const actions = {

    /**
     * Get all countries data + global metadata
     */
    async GET_COUNTRIES (context)
    {
        await axios.get('countries')
        .then(response => {
            console.log('get_countries', response);

            context.commit('setCountries', response.data);
        })
        .catch(error => {
            console.log('error.get_countries', error);
        });
    },

    /**
     * Get all states for a country
     */
    async GET_STATES (context, payload)
    {
        await axios.get(window.location.origin + '/states', {
            params: {
                country: payload
            }
        })
        .then(response => {
            console.log('get_states', response);

            context.commit('setStates', response.data);
        })
        .catch(error => {
            console.log('error.get_states', error);
        });
    },

    /**
     * Get all cities for a state, country
     */
    async GET_CITIES (context, payload)
    {
        console.log('get_cities', payload);
        await axios.get(window.location.origin + '/cities', {
            params: {
                country: payload.country,
                state: payload.state
            }
        })
        .then(response => {
            console.log('get_cities', response);

            context.commit('setCities', response.data);
        })
        .catch(error => {
            console.log('error.get_cities', error);
        });
    }
};