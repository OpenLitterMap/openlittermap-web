import { defineStore } from 'pinia';
import axios from 'axios';

export const usePlansStore = defineStore('plans', {
    state: () => ({
        plans: [
            { id: 1, name: 'Starter', price: 0, plan_id: null },
            { id: 2, name: 'Startup', price: 500, plan_id: 'price_startup' },
            { id: 3, name: 'Basic', price: 1500, plan_id: 'price_basic' },
            { id: 4, name: 'Advanced', price: 3000, plan_id: 'price_advanced' },
            { id: 5, name: 'Pro', price: 5000, plan_id: 'price_pro' },
        ],
        errors: {},
        loading: false,
    }),

    actions: {
        async fetchPlans() {
            try {
                this.loading = true;
                // const { data } = await axios.get('/api/plans')
                // this.plans = data
            } catch (e) {
                // keep defaults
            } finally {
                this.loading = false;
            }
        },

        clearError(key) {
            if (this.errors?.[key]) {
                const next = { ...this.errors };
                delete next[key];
                this.errors = next;
            }
        },

        async createAccount(payload) {
            this.errors = {};
            try {
                await axios.post('/api/auth/register', payload);
            } catch (error) {
                if (error?.response?.status === 422) {
                    this.errors = error.response.data.errors || {};
                } else {
                    this.errors = { general: ['Something went wrong. Please try again.'] };
                }
                throw error;
            }
        },
    },
});
