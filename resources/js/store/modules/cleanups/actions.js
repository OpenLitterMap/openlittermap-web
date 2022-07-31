export const actions = {
    /**
     *
     */
    async CREATE_CLEANUP_EVENT (context, payload)
    {
        await axios.post('/cleanups/create', {
            name: payload.name,
            date: payload.date,
            lat: payload.lat,
            lon: payload.lon,
            time: payload.time,
            description: payload.description,
            inviteLink: payload.inviteLink
        })
        .then(response => {
            console.log('create_cleanup_event', response);

            if (response.data.success)
            {

            }
        })
        .catch(error => {
            console.error('create_cleanup_event', error);
        });
    }
}
