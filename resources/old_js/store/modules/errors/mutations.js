export const mutations = {
    /**
     * Delete one of the userErrors key-value pairs by key
     */
    clearError (state, payload)
    {
        if (state.errorsObject)
        {
            delete state.errorsObject[payload];

            if (Object.keys(state.errorsObject).length === 0)
            {
                state.errorsObject = null;
            }
        }
    },

    /**
     * Delete all errors
     */
    clearErrors (state)
    {
        state.errorsObject = null;
    },

    /**
     * When the modal closes, remove the errors object
     */
    hideModal (state)
    {
        state.errorsObject = null;
    },

    /**
     * Errors object has been received from the database
     */
    setErrors (state, payload)
    {
        state.errorsObject = payload;
    },

    /**
     * Custom error used when clearing error on CreateCleanup component
     */
    setCleanupLocation (state)
    {
        if (state.errorsObject?.hasOwnProperty('lat')) {
            delete state.errorsObject['lat'];
        }

        if (state.errorsObject?.hasOwnProperty('lon')) {
            delete state.errorsObject['lon'];
        }
    }
}
