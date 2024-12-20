export default {
    computed: {
        /**
         * Errors object
         * Default: null
         *
         * @returns {null|*}
         */
        errors ()
        {
            return this.$store.state.errors.errorsObject;
        },

        /**
         * Return True if errors exist
         */
        errorsExist ()
        {
            return (this.errors && Object.keys(this.errors).length > 0)
        }
    },
    methods: {
        /**
         * Clear an error with this key
         */
        clearError (key)
        {
            if (this.errors && this.errors[key])
            {
                this.$store.commit('clearError', key);
            }
        },

        /**
         * Check if any errors exist for this key
         */
        errorExists (key)
        {
            if (this.errors) {
                return this.errors.hasOwnProperty(key);
            }

            return false;
        },

        /**
         * Get the first error from errors object
         */
        getFirstError (key)
        {
            return this.errors[key][0];
        },
    }
}
