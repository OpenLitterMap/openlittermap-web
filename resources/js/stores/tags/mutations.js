export const mutations = {
    /**
     * Set all pre-defined tags for tagging
     */
    setObjectsForCategories() {
        this.categories.forEach((category) => {
            const allTagsForCategory = this.tags.filter(
                (tag) => tag.key === category.key
            );

            if (
                allTagsForCategory.length > 0 &&
                allTagsForCategory[0].litter_objects
            ) {
                this.objectsForCategory[category.key] =
                    allTagsForCategory[0].litter_objects;
            } else {
                this.objectsForCategory[category.key] = [];
            }
        });
    },
};
