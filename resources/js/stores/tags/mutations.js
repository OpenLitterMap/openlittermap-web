export const mutations = {
    initTags({ groupedTags, sortedTags }) {
        // Tags in their nested format.
        this.groupedTags = groupedTags;

        // Tags in their native, non-nested format.
        this.categories = sortedTags.categories;
        this.objects = sortedTags.objects;
        this.tagTypes = sortedTags.tagTypes;
        this.materials = sortedTags.materials;

        this.setObjectsForCategories();
    },

    /**
     * Set all pre-defined tags for tagging
     */
    setObjectsForCategories() {
        this.categories.forEach((category) => {
            const allTagsForCategory = this.groupedTags.filter((tag) => tag.key === category.key);

            if (allTagsForCategory.length > 0 && allTagsForCategory[0].litter_objects) {
                this.objectsForCategory[category.key] = allTagsForCategory[0].litter_objects;
            } else {
                this.objectsForCategory[category.key] = [];
            }
        });
    },
};
