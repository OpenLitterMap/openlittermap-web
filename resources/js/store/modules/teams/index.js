import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    error: '',
    errors: {},
    types: []
};

export const teams = {
    state,
    actions,
    mutations
}
