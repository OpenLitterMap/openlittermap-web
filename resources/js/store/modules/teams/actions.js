import Vue from 'vue';
import i18n from '../../../i18n';
import router from '../../../routes';

export const actions = {

    /**
     * Change what team the user is currently a part of
     */
    async CHANGE_ACTIVE_TEAM (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body = 'Your active team has changed'; // insert new active team name

        await axios.post('/teams/active', {
            team_id: payload
        })
            .then(response =>
            {
                console.log('change_active_team', response);

                if (response.data.success)
                {
                    Vue.$vToastify.success({
                        title,
                        body,
                        position: 'top-right'
                    });

                    context.commit('usersActiveTeam', payload);
                }
            })
            .catch(error =>
            {
                console.error('change_active_team', error);
            });
    },

    /**
     * The user wants to create a new team
     */
    async CREATE_NEW_TEAM (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body  = i18n.t('teams.created');

        const error_title = i18n.t('notifications.error');
        const error_body  = i18n.t('teams.create.max-created');

        const joinedTeamTitle = i18n.t('notifications.success');
        const joinedTeamBody = 'Congratulations! You have joined a new team!'; // todo - insert team name

        await axios.post('/teams/create', {
            name: payload.name,
            identifier: payload.identifier,
            teamType: payload.teamType
        })
            .then(response =>
            {
                console.log('create_new_team', response);

                if (response.data.success)
                {
                    Vue.$vToastify.success({
                        title,
                        body,
                        position: 'top-right'
                    });

                    context.commit('decrementUsersRemainingTeams');

                    if (! context.rootState.user.user.active_team)
                    {
                        context.commit('usersActiveTeam', response.data.team_id);

                        Vue.$vToastify.success({
                            title: joinedTeamTitle,
                            body: joinedTeamBody,
                            position: 'top-right'
                        });
                    }
                }

                else
                {
                    Vue.$vToastify.error({
                        title: error_title,
                        body: error_body,
                        position: 'top-right'
                    });
                }
            })
            .catch(error =>
            {
                console.error('create_new_team', error.response.data.errors);

                context.commit('teamErrors', error.response.data.errors);
            });
    },

    /**
     * Get the combined effort for all of the users teams for this period
     */
    async GET_COMBINED_TEAM_EFFORT (context, payload)
    {
        await axios.get('/teams/combined-effort', {
            params: {
                period: payload
            }
        })
            .then(response =>
            {
                console.log('get_combined_teams_effort', response);

                context.commit('combinedTeamEffort', response.data);
            })
            .catch(error =>
            {
                console.error('get_combined_teams_effort', error);
            });
    },

    /**
     * Get paginated team members for team_id
     */
    async GET_TEAM_MEMBERS (context, payload)
    {
        await axios.get('/teams/members', {
            params: {
                team_id: payload
            }
        })
            .then(response =>
            {
                console.log('get_team_members', response);

                context.commit('paginatedTeamMembers', response.data.result);
            // todo - take out total_members into separate request
            })
            .catch(error =>
            {
                console.error('get_team_members', error);
            });
    },

    /**
     * Get all team types from DB
     */
    async GET_TEAM_TYPES (context)
    {
        await axios.get('/teams/get-types')
            .then(response =>
            {
                console.log('get_team_types', response);

                context.commit('teamTypes', response.data);
            })
            .catch(error =>
            {
                console.error('get_team_types', error);
            });
    },

    /**
     * Get an array of all teams the user has joined
     */
    async GET_USERS_TEAMS (context)
    {
        await axios.get('/teams/joined')
            .then(response =>
            {
                console.log('get_users_teams', response);

                context.commit('usersTeams', response.data);
            })
            .catch(error =>
            {
                console.error('get_users_teams', error);
            });
    },

    /**
     * The user wants to join a team with an identifier
     *
     * Not to be confused with change active team.
     */
    async JOIN_TEAM (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body = 'Congratulations! You have joined a new team!'; // todo - insert team name, translate

        const failTitle = i18n.t('notifications.error');
        const failBody = 'Sorry, we could not find a team with this identifier.'; // todo - insert identifier, translate

        const alreadyJoinedbody = 'You have already joined this team!'; // todo - translate

        await axios.post('/teams/join', {
            identifier: payload
        })
            .then(response =>
            {
                console.log('join_team', response);

                // show notification
                if (response.data.success)
                {
                // success
                    Vue.$vToastify.success({
                        title,
                        body,
                        position: 'bottom-right'
                    });
                    context.commit('hideModal');
                }

                else if (response.data.msg === 'already-joined')
                {
                    Vue.$vToastify.info({
                        title: 'Hold on!',
                        body: alreadyJoinedbody,
                        position: 'bottom-right'
                    });
                }

                else
                {
                    Vue.$vToastify.error({
                        title: failTitle,
                        body: failBody,
                    });
                }
            })
            .catch(error =>
            {
                console.error('join_team', error);

                context.commit('teamErrors', error.response.data.errors);
            });
    },

    /**
     * Load the previous page of members
     */
    async PREVIOUS_MEMBERS_PAGE (context, payload)
    {
        await axios.get(context.state.members.prev_page_url, {
            params: {
                team_id: payload
            }
        })
            .then(response => {
                console.log('previous_members_page', response);

                context.commit('paginatedTeamMembers', response.data.result);
            })
            .catch(error => {
                console.error('previous_members_page', error);
            });
    },

    /**
     * Load the next page of members
     */
    async NEXT_MEMBERS_PAGE (context, payload)
    {
        await axios.get(context.state.members.next_page_url, {
            params: {
                team_id: payload
            }
        })
            .then(response => {
                console.log('next_members_page', response);

                context.commit('paginatedTeamMembers', response.data.result);
            })
            .catch(error => {
                console.error('next_members_page', error);
            });
    }

};
