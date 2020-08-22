import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    errors: {},
    just_subscribed: false // show Success notification when just subscribed
};

export const subscriber = {
    state,
    actions,
    mutations
}
