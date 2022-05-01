import { actions } from "./actions";
import { mutations } from "./mutations";

const state = {
    currentPage: 0,
    users: null
};

export const leaderboard = {
    state,
    actions,
    mutations
}
