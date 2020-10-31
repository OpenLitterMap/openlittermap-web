import Vue from 'vue'
import i18n from '../../../i18n'

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

        await axios.post('download', {
            type: payload,
            country: context.state.country,
            state: context.state.state,
            city: context.state.city
        })
        .then(response => {
            console.log('download_data', response);

            if (response.data.success)
            {
                // not showing?
                /* improve this */
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });
            }

            else {
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
