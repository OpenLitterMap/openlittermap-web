// import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    action: '', // action to dispatch
    button: '', // text on the button to display
    show: false,
    title: '',
    type: '',
}

export const modal = {
    state,
    mutations
}
