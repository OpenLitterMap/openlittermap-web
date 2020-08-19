import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    plan: '',
    plans: []
};

export const plans = {
    state,
    actions,
    mutations
};