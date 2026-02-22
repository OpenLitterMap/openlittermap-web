import Vue from 'vue'
import i18n from '../../../i18n'
import router from "../../../routes";

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
        .then(response => {
            console.log('change_active_team', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.commit('usersActiveTeam', payload);
                context.commit('usersTeam', response.data.team);
            }
        })
        .catch(error => {
            console.error('change_active_team', error);
        });
    },

    /**
     * Leave the team
     */
    async LEAVE_TEAM (context, payload)
    {
        let title = i18n.t('notifications.success');
        let body = i18n.t('teams.myteams.just-left-team');

        let titleError = i18n.t('notifications.error');
        let bodyError = i18n.t('notifications.something-went-wrong');

        await axios.post('/teams/leave', {
            team_id: payload
        })
        .then(response => {
            console.log('leave_team', response);

            if (response.data.success) {
                Vue.$vToastify.success({
                    title: title,
                    body: body + ' <i>' + response.data.team.name + '</i>.',
                });

                if (response.data.activeTeam) {
                    context.commit('usersActiveTeam', response.data.activeTeam.id);
                    context.commit('usersTeam', response.data.activeTeam);
                }
            }
        })
        .catch(error => {
            Vue.$vToastify.error({
                title: titleError,
                body: bodyError,
            });
        });
    },

    /**
     * Inactivate the current active team
     */
    async INACTIVATE_TEAM (context)
    {
        let titleError = i18n.t('notifications.error');
        let bodyError = i18n.t('notifications.something-went-wrong');

        await axios.post('/teams/inactivate')
        .then(response => {
            console.log('inactivate_team', response);

            if (response.data.success) {
                context.commit('usersActiveTeam', null);
                context.commit('usersTeam', null);
            }
        })
        .catch(error => {
            Vue.$vToastify.error({
                title: titleError,
                body: bodyError,
            });
        });
    },

    /**
     * The user wants to create a new team
     */
    async CREATE_NEW_TEAM (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body  = i18n.t('teams.create.created');

        const error_title = i18n.t('notifications.error');
        const error_body  = i18n.t('teams.create.max-created');

        const joinedTeamTitle = i18n.t('notifications.success');
        const joinedTeamBody = 'Congratulations! You have joined a new team!'; // todo - insert team name

        await axios.post('/teams/create', {
            name: payload.name,
            identifier: payload.identifier,
            team_type: payload.teamType
        })
        .then(response => {
            console.log('create_new_team', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.commit('decrementUsersRemainingTeams');

                context.commit('usersActiveTeam', response.data.team.id);

                context.commit('usersTeam', response.data.team);

                if (! context.rootState.user.user.active_team)
                {
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
        .catch(error => {
            console.error('create_new_team', error.response.data.errors);

            context.commit('teamErrors', error.response.data.errors);
        });
    },

    /**
     * The user wants to update a team
     */
    async UPDATE_TEAM (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body  = i18n.t('teams.create.updated');

        const errorTitle = i18n.t('notifications.error');
        const errorBody  = i18n.t('notifications.something-went-wrong');

        await axios.post(`/teams/update/${payload.teamId}`, {
            name: payload.name,
            identifier: payload.identifier
        })
            .then(async response => {
                console.log('update_team', response);

                if (response.data.success) {
                    Vue.$vToastify.success({title, body});
                } else {
                    Vue.$vToastify.error({title: errorTitle, body: errorBody});
                }
            })
            .catch(error => {
                context.commit('teamErrors', error.response.data.errors);
            });
    },

    /**
     * Download the data made by a specific team
     */
    async DOWNLOAD_DATA_FOR_TEAM (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body = 'Your download is being processed and will be emailed to you shortly';

        await axios.post('/teams/download', {
            team_id: payload
        })
        .then(response => {
            console.log('download_data_for_team', response);

            if (response.data.success)
            {
                // success
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'bottom-right'
                });
            }
        })
        .catch(error => {
            console.error('download_data_for_team', error);
        });
    },

    /**
     * Get the combined effort for all of the users teams for this period
     *
     * team.id 0 => all teams
     */
    async GET_TEAM_DASHBOARD_DATA (context, payload)
    {
        await axios.get('/teams/data', {
            params: {
                period: payload.period,
                team_id: payload.team_id
            }
        })
        .then(response => {
            console.log('get_team_dashboard_data', response);

            context.commit('teamDashboardData', response.data);
        })
        .catch(error => {
            console.error('get_team_dashboard_data', error);
        });
    },

    /**
     * Get all teams for a global team leaderboard
     */
    async GET_TEAMS_LEADERBOARD (context)
    {
        await axios.get('/teams/leaderboard')
            .then(response => {
                console.log('get_teams_leaderboard', response);

                context.commit('teamsLeaderboard', response.data);
            })
            .catch(error => {
                console.error('get_teams_leaderboard', error);
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
        .then(response => {
            console.log('get_team_members', response);

            context.commit('paginatedTeamMembers', response.data.result);
            // todo - take out total_members into separate request
        })
        .catch(error => {
            console.error('get_team_members', error);
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
     * Get an array of all teams the user has joined
     */
    async GET_USERS_TEAMS (context)
    {
        await axios.get('/teams/joined')
            .then(response => {
                console.log('get_users_teams', response);

                context.commit('usersTeams', response.data);
            })
            .catch(error => {
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

        const alreadyJoinedbody = 'You have already joined this team!'; // todo - translate

        await axios.post('/teams/join', {
            identifier: payload
        })
        .then(response => {
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

                context.commit('usersActiveTeam', response.data.activeTeam.id);
                context.commit('usersTeam', response.data.activeTeam);
            }

            else if (response.data.msg === 'already-joined')
            {
                Vue.$vToastify.info({
                    title: 'Hold on!',
                    body: alreadyJoinedbody,
                    position: 'bottom-right'
                });
            }
        })
        .catch(error => {
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
    },

    /**
     * Save the privacy settings for 1 team or all teams
     *
     * team_id
     * all @bool
     */
    async SAVE_TEAM_SETTINGS (context, payload)
    {
        const settings = context.state.teams.find(team => team.id === payload.team_id).pivot;

        const title = i18n.t('notifications.success');
        const body = 'Team settings updated';

        await axios.post('/teams/settings', {
            settings,
            all: payload.all,
            team_id: payload.team_id
        })
        .then(response => {
            console.log('save_team_settings', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                if (payload.all) context.commit('allTeamSettings', payload.team_id);
            }
        })
        .catch(error => {
            console.error('save_team_settings', error);
        });
    },

    /**
     * Show or Hide the Team on the shared TeamsLeaderboard
     */
    async TOGGLE_LEADERBOARD_VISIBILITY (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body = 'Visibility changed';

        await axios.post('/teams/leaderboard/visibility', {
            team_id: payload
        })
        .then(response => {
            console.log('toggle_leaderboard_visibility', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.commit('toggleTeamLeaderboardVis', payload);
            }
        })
        .catch(error => {
            console.error('toggle_leaderboard_visibility', error);
        });
    }

}
