export const mutations = {
    initTags(tags) {
        // Tags in their nested format.
        this.groupedTags = tags;
    },

    initAllTags({ categories, objects, materials, brands, types, category_objects, category_object_types }) {
        this.categories = categories;
        this.objects = objects;
        this.materials = materials;
        this.brands = brands;
        this.types = types || [];
        this.categoryObjects = category_objects || [];
        this.categoryObjectTypes = category_object_types || [];
    },
};
