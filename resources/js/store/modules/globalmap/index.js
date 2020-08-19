import { actions } from './actions'
// import { getters } from './getters'
import { mutations } from './mutations'

const state = {
  currentDate: 'today',
  loading: false, // reload component
  datesOpen: false, // change dates box on global map
  langsOpen: false,
  globalMapData: []
};

export const globalmap = {
  state,
  actions,
  // getters,
  mutations
}