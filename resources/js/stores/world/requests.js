export const requests = {
    async GET_WORLD_CUP_DATA() {
        await axios
            .get('/get-world-cup-data')
            .then((response) => {
                console.log('get_world_cup_data', response);

                this.locations = response.data.countries;
                this.globalLeaders = response.data.globalLeaders;
                this.total_litter = response.data.total_litter;
                this.total_photos = response.data.total_photos;
                this.level.previousXp = response.data.previousXp;
                this.level.nextXp = response.data.nextXp;
                this.littercoin = response.data.littercoin;
            })
            .catch((error) => {
                console.log('error.get_world_cup_data', error);
            });
    },
};
