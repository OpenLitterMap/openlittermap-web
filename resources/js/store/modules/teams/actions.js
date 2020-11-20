import Vue from 'vue'
import i18n from '../../../i18n'

export const actions = {

    /**
     * The user wants to create a new team
     */
    async CREATE_NEW_TEAM (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body  = i18n.t('settings.teams.created');

        await axios.post('/teams/create', {
            name: payload.name,
            identifier: payload.identifier,
            teamType: payload.teamType
        })
        .then(response => {
            console.log('create_new_team', response);

            // show notification
            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });

            // add team to teams array
            // context.commit('usersTeams', response.data.team);

            // If user does not have an active team, assign
            if (! context.rootState.user.user.team_id)
            {
                context.commit('userJoinTeam', response.data.team);
            }
        })
        .catch(error => {
            console.error('create_new_team', error.response.data.errors);

            context.commit('teamErrors', error.response.data.errors);
        });
    },

    /**
     * Get all team types from DB
     */
    async GET_TEAM_TYPES (context)
    {
        await axios.get('/teams/get-types')
            .then(response => {
                console.log('get_team_types', response);

                context.commit('teamTypes', response.data);
            })
            .catch(error => {
                console.error('get_team_types', error);
            });
    },

    /**
     * The user wants to join a team with an identifier
     */
    async JOIN_TEAM (context, payload)
    {
        await axios.post('/teams/join', {
            identifier: payload.identifier
        })
            .then(response => {
                console.log('join_team', response);

                // update translation with response

                // show notification
            })
            .catch(error => {
                console.error('join_team', error);
            });
    }
}
