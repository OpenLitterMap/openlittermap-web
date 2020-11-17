export const actions = {

    /**
     * The user wants to create a new team
     */
    async CREATE_NEW_TEAM (context)
    {
        await axios.post('/teams/create', {

        })
    },


    /**
     * Get all team types from DB
     */
    async GET_TEAM_TYPES (context)
    {
        await axios.get('/teams/get-types')
            .then(response => {
                console.log('get_team_teams', response);
            })
            .catch(error => {
                console.error('get_team_types', error);
            });
    }
}
