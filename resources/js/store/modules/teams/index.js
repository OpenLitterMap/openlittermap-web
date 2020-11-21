import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    component_type: 'Default',
    error: '',
    errors: {},
    types: []
};

export const teams = {
    state,
    actions,
    mutations
}
