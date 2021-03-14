import { categories } from '../../../../extra/categories'
import { litterkeys } from '../../../../extra/litterkeys'

export const mutations = {

    /**
     * When a box was clicked
     *
     * Find box that was selected
     *
     * Turn active => true
     *
     * @payload id
     */
    activateBox (state, payload)
    {
        state.boxes.map(box => {
            box.active = box.id === payload;

            return box;
        });
    },

    /**
     * Add a new bounding box
     *
     * We +1 the last ID in case a box was deleted
     */
    addNewBox (state)
    {
        let boxes = state.boxes;

        const id = (state.boxes.length === 0)
            ? 1
            : state.boxes[state.boxes.length -1].id + 1

        boxes.push({
            id,
            top: 0,
            left: 0,
            height: 100,
            width: 100,
            active: false,
            category: null,
            tag: null,
            brand: null
        });
    },

    /**
     * Add tag to a box
     *
     * Payload = {
     *     category: {
     *         tag: quantity
     *     }
     * }
     *
     * eg { smoking: { butts: 10 } }
     *
     * Note: We need Quantity number of boxes per tag (eg 10 butts => 10 boxes)
     */
    addTagToBox (state, payload)
    {
        let boxes = [...state.boxes];

        let box = boxes.find(box => box.active);

        box.category = payload.category;
        box.tag = payload.tag;

        if (payload.hasOwnProperty('brand'))
        {
            box.brand = payload.brand;
        }

        state.boxes = boxes;
    },

    /**
     * When an image has tags, we start with [] boxes
     */
    clearBoxes (state)
    {
        state.boxes = [];
    },

    /**
     * Turn box.active => false for all boxes
     */
    deactivateBoxes (state)
    {
        state.boxes.map(box => box.active = false);
    },

    /**
     * Todo - Duplicate a box
     *
     * Bug: not relative to parent photo
     */
    duplicateBox (state, payload)
    {
        let boxes = [...state.boxes];

        let box = boxes.find(box => box.id === payload);

        let newBox = _.cloneDeep(box);

        newBox.id = boxes.length + 1;
        newBox.top = 0;
        newBox.left = 0;

        boxes.push(newBox);

        state.boxes = boxes;
    },

    /**
     * Using the result string,
     *
     * Split up the result and add Quantity number of bounding_boxes,
     * with the associated category labels
     */
    initBboxTags (state, payload)
    {
        // state.boxes = []
        this.commit('clearBoxes');

        categories.map(category => {

            if (payload[category])
            {
                litterkeys[category].map(tag => {

                    if (payload[category][tag])
                    {
                        const quantity = payload[category][tag];

                        console.log(category, tag, quantity);

                        let i = 1;

                        while (i <= quantity)
                        {
                            this.commit('addNewBox');

                            // Get the last / most recently created box.id
                            const box_id = state.boxes[state.boxes.length -1].id;

                            // activate this box so we can add tags to it
                            // all other boxes will be deactivated
                            this.commit('activateBox', box_id);

                            // add category, tag to the active box
                            this.commit('addTagToBox', { category, tag });

                            i++;
                        }

                    }

                });
            }
        });
    },

    // /**
    //  * Todo
    //  */
    // moveBoxUp (state)
    // {
    //     let boxes = [...state.boxes];
    //
    //     boxes.map(box => {
    //         if (box.active)
    //         {
    //             box.top--;
    //
    //             console.log('box.top', box.top);
    //         }
    //
    //         return box;
    //     })
    //
    //     state.boxes = boxes;
    // },

    /**
     * Filter out any boxes that are active
     */
    removeActiveBox (state)
    {
        let boxes = [...state.boxes];

        boxes = boxes.filter(box => box.active === false);

        // Todo - Reset the ID of each box to stay in order
        // let id = 1;
        //
        // boxes.map(box => {
        //     box.id = id;
        //     id++;
        //     return box;
        // });

        state.boxes = boxes;
    },

    /**
     * Remove a tag from a bounding box
     */
    removeBboxTag (state, payload)
    {
        let boxes = [...state.boxes];

        boxes.map(box => {

            if (box.active)
            {
                let tags = Object.assign({}, box.tags);

                delete tags[payload.category][payload.tag_key];

                if (Object.keys(tags[payload.category]).length === 0)
                {
                    delete tags[payload.category];
                }
            }

            return box;
        });

        state.boxes = boxes;
    },

    /**
     * Update the coordinates of active box
     */
    updateBoxPosition (state, payload)
    {
        state.boxes.map(box => {

            if (box.active)
            {
                box.width = payload.width;
                box.height = payload.height;
                box.top = payload.top;
                box.left = payload.left;
            }

            return box;
        });
    }
}
