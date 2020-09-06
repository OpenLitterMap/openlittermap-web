import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    errors: {},
    plan: '',
    plans: []
};

export const plans = {
    state,
    actions,
    mutations
}
