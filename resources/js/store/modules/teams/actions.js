export const actions = {

    /**
     * The user wants to create a new team
     */
    async CREATE_NEW_TEAM (context, payload)
    {
        await axios.post('/teams/create', {
            name: payload.name,
            identifier: payload.identifier,
            teamType: payload.teamType
        })
        .then(response => {
            console.log('create_new_team', response);

            // update translation with response

            // show notification
        })
        .catch(error => {
            console.error('get_team_types', error);
        });
    },

    /**
     * Get all team types from DB
     */
    async GET_TEAM_TYPES (context)
    {
        await axios.get('/teams/get-types')
            .then(response => {
                console.log('create_new_team', response);


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
