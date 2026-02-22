import Vue from "vue";
import i18n from "../../../../i18n";

export const actions = {
    /**
     * Admin & Helper can create Merchants
     *
     * They need to be approved by Admins
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

                context.commit('setMerchant', response.data.merchant);
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

    /**
     * Admin only
     */
    async GET_NEXT_MERCHANT_TO_APPROVE (context)
    {
        await axios.get('/merchants/get-next-merchant-to-approve')
            .then(response => {
                console.log('get_next_merchant_to_approve', response);

                if (response.data.success) {
                    context.commit('setMerchant', response.data.merchant);
                } else {
                    context.commit('resetMerchant');
                }
            })
            .catch(error => {
                console.error('get_next_merchant_to_approve', error);
            });
    },

    /**
     * Admin--
     *
     * Approve a merchant that was created by a helper
     */
    async APPROVE_MERCHANT (context)
    {
        const title = i18n.t('notifications.success');
        const body = 'Merchant approved';

        await axios.post('/admin/merchants/approve', {
            merchantId: context.state.merchant.id
        })
        .then(response => {
            console.log('approve_merchant', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.dispatch('GET_NEXT_MERCHANT_TO_APPROVE');
            }
        })
        .catch(error => {
            console.error('approve_merchant', error);
        });
    },

    /**
     * Admin--
     *
     * Delete a merchant that was added by a helper
     */
    async DELETE_MERCHANT (context)
    {
        const title = i18n.t('notifications.success');
        const body = 'Merchant deleted';

        await axios.post('/admin/merchants/delete', {
            merchantId: context.state.merchant.id
        })
        .then(response => {
            console.log('delete_merchant', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.dispatch('GET_NEXT_MERCHANT_TO_APPROVE');
            }
            else if (response.data.msg === 'does not exist')
            {
                context.commit('resetMerchant');
            }
        })
        .catch(error => {
            console.error('delete_merchant', error);
        });
    }
}
