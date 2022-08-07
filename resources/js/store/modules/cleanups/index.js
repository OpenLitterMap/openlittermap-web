import { actions } from "./actions";
import { mutations } from "./mutations";

const state = {
    creating: false,
    joining: false,
    lat: null,
    lon: null,
    geojson: null
};

export const cleanups = {
    state,
    actions,
    mutations
}
