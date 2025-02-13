export const mutations = {
    initTags(tags) {
        // Tags in their nested format.
        this.groupedTags = tags;
    },

    initAllTags({ categories, objects, materials, brands }) {
        this.categories = categories;
        this.objects = objects;
        this.materials = materials;
        this.brands = brands;
    },
};
