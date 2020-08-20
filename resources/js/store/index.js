import Vue from 'vue'
import Vuex from 'vuex'

import { admin } from './modules/admin'
import { donate } from './modules/donate'
import { globalmap } from './modules/globalmap'
import { locations } from './modules/locations'
import { litter } from './modules/litter'
import { modal } from './modules/modal'
import { plans } from './modules/plans'
import { subscriber} from './modules/subscriber'
import { user } from './modules/user'

Vue.use(Vuex)

export default new Vuex.Store({
    modules: {
        admin,
        donate,
        globalmap,
        locations,
        litter,
        modal,
        plans,
        subscriber,
        user
    }
});
