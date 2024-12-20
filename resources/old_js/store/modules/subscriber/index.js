import { init } from './init'
import { actions } from './actions'
import { mutations } from './mutations'

// maybe this should be on user.subscriber
const state = Object.assign({}, init);

export const subscriber = {
    state,
    actions,
    mutations
}
