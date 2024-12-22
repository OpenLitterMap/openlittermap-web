export const mutations = {

    clearErrors () {
        this.errors = {};
    },

    subscribeErrors (payload) {
        this.errors = payload;
    },

    updatedJustSubscribed (payload) {
        this.justSubscribed = payload;
    }

};
