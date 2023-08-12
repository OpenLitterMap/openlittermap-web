import Vue from "vue";
import i18n from "../../../../i18n";

export const actions = {
    /**
     * Admin\Helper Only
     */
    async CREATE_MERCHANT (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body = 'Merchant created';

        await axios.post('/merchants/create', {
            name: payload.name,
            address: payload.address,
            lat: payload.lat,
            lon: payload.lon,
            email: payload.email,
            about: payload.about,
            website: payload.website
        })
        .then(response => {
            console.log('admin_create_merchant', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });
            }
        })
        .catch(error => {
            console.error('admin_create_merchant', error);
        });
    },

    /**
     * Get GeoJson cleanups object
     */
    async GET_MERCHANTS_GEOJSON (context)
    {
        await axios.get('/merchants/get-geojson')
            .then(response => {
                console.log('get_merchants_geojson', response);

                if (response.data.success)
                {
                    context.commit('setMerchantsGeojson', response.data.geojson);
                }
            })
            .catch(error => {
                console.error('get_merchants_geojson', error);
            });
    },
}
