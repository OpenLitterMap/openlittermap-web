export const mutations = {

    createLitterObject (state, payload) {
        const id = state.objects.length;

        state.objects.push({
            id,
            category: null,
            object: null,
            brand: null,
            tag_type: null,
            quantity: null,
            picked_up: payload.pickedUp,
            materials: [],
            custom_tags: []
        });

        state.selectedObjectId = id;
    },

    addTagToObject (state, payload) {

        let objs = [...state.objects];

        let obj = Object.assign(objs[state.selectedObjectId]);

        if (!obj.category) {
            obj.category = payload.category;
        }

        if (!obj.object) {
            obj.object = payload.object;
        }

        if (!obj.brand) {
            obj.brand = payload.brand
        }

        if (payload.category !== 'brands') {
            obj.quantity = payload.quantity;
        }

        obj.picked_up = payload.pickedUp;

        state.objects = objs;
    },

    changeObjectSelected (state, payload) {
        state.selectedObjectId = payload;
    },

    deleteLitterObject (state, payload) {
        state.objects = state.objects.filter(obj => obj.id !== payload);
    }
}
