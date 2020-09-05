import { actions } from './actions'
import { mutations } from './mutations'

// maybe this should be on user
const state = {
    current_plan: {},
    current_subscription: {},
    errors: {},
    just_subscribed: false, // show Success notification when just subscribed
};

export const subscriber = {
    state,
    actions,
    mutations
}
