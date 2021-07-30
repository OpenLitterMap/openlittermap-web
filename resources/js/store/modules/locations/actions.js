import Vue from 'vue'
import i18n from '../../../i18n'
import routes from '../../../routes';

export const actions = {

    /**
     * Download data for a location
     *
     * @payload (type|string) is location_type. eg 'country', 'state' or 'city'
     */
    async DOWNLOAD_DATA (context, payload)
    {
        let title = i18n.t('notifications.success');
        let body  = 'Your download is being processed and will be emailed to you soon';

        await axios.post('/download', {
            type: payload.type,
            locationId: payload.locationId,
            email: payload.email
        })
        .then(response => {
            console.log('download_data', response);

            if (response.data.success)
            {
                /* improve this */
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });
            }
            else
            {
                /* improve this */
                Vue.$vToastify.success({
                    title: 'Error',
                    body: 'Sorry, there was an error with the download. Please contact support',
                    position: 'top-right'
                });
            }


        })
        .catch(error => {
            console.error('download_data', error);
        });
    },

    // We don't need this yet but we might later
    // /**
    //  * Load the data for any location
    //  */
    // async GET_LOCATION_DATA (context, payload)
    // {
    //     await axios.get('location', {
    //         params: {
    //             locationType: payload.locationType,
    //             id: payload.id
    //         }
    //     })
    //     .then(response => {
    //         console.log('get_location_data', response);
    //
    //         if (payload.locationType === 'country')
    //         {
    //             context.commit('setStates', response.data)
    //
    //             routes.push('/world/' + response.data.countryName);
    //         }
    //         else if (payload.locationType === 'state')
    //         {
    //             context.commit('setCities', response.data)
    //         }
    //         else if (payload.locationType === 'city')
    //         {
    //             console.log('set cities?');
    //         }
    //         else
    //         {
    //             console.log('wrong location type');
    //         }
    //
    //         // router.push({ path:  '/world/' + response.data.countryName });
    //
    //     })
    //     .catch(error => {
    //         console.log('get_location_data', error);
    //     });
    // },

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
        await axios.get('/states', {
            params: {
                country: payload
            }
        })
        .then(response => {
            console.log('get_states', response);

            if (response.data.success)
            {
                context.commit('countryName', response.data.countryName);

                context.commit('setLocations', response.data.states)
            }
            else
            {
                routes.push({ 'path': '/world' });
            }
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
        await axios.get('/cities', {
            params: {
                country: payload.country,
                state: payload.state
            }
        })
        .then(response => {
            console.log('get_cities', response);

            if (response.data.success)
            {
                context.commit('countryName', response.data.country);
                context.commit('stateName', response.data.state);

                context.commit('setLocations', response.data.cities)
            }
            else
            {
                routes.push({ 'path': '/world' })
            }
        })
        .catch(error => {
            console.log('error.get_cities', error);
        });
    }
};
