import { defineStore } from 'pinia'
import { actions } from './actions'
import { mutations } from "./mutations.js";

export const useSubscriberStore = defineStore('subscriber', {

    state: () => ({
        errors: {},
        justSubscribed: false,
        subscription: {}
    }),

    getters: {},

    actions: {
        ...actions,
        ...mutations
    }

});
