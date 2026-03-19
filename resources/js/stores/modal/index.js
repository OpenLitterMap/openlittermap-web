import { defineStore } from "pinia";

export const useModalStore = defineStore("modal", {

    state: () => {
        return {
            action: '', // action to dispatch
            button: '', // text on the button to display
            show: false,
            title: '',
            type: '',
        };
    },

    actions: {
        /**
         * Hide the modal
         */
        hideModal ()
        {
            this.show = false;
        },

        /**
         * Reset state, when the user logs out
         */
        resetState ()
        {
            this.$reset();
        },

        /**
         * Show the modal
         */
        showModal (payload)
        {
            this.type = payload.type;
            this.title = payload.title;
            this.action = payload.action;
            this.show = true;
        }
    }
});
