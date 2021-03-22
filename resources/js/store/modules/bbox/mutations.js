import { categories } from '../../../extra/categories'
import { litterkeys } from '../../../extra/litterkeys'

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
     * When a brand tag is received from the backend,
     *
     * We need to put it into a separate brands array,
     *
     * And let the user drag it into the correct box
     */
    addBoxBrand (state, payload)
    {
        let brands = [...state.brands];

        brands.push(payload);

        state.brands = brands;
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
     * Add brand to a box
     */
    addSelectedBrandToBox (state, payload)
    {
        let boxes = [...state.boxes];

        let box = boxes.find(box => box.id === payload);

        let brands = [...state.brands];

        box.brand = brands[state.selectedBrandIndex];

        console.log({ box });

        brands.splice(state.selectedBrandIndex, 1);

        console.log({ brands });

        state.brands = brands;
        state.boxes = boxes;
        state.selectedBrandIndex = null;
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
     * Update total boxes tagged by this user and all users
     */
    bboxCount (state, payload)
    {
        state.usersBoxCount = payload.usersBoxCount;
        state.totalBoxCount = payload.totalBoxCount;
    },

    /**
     * Draggable
     */
    setBrandsBox (state, payload)
    {
        state.brands = payload;
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
     *
     * Disable selectedBrand if exists
     */
    deactivateBoxes (state)
    {
        state.boxes.map(box => box.active = false);

        state.selectedBrandIndex = null;
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
     * Create 1 for for every tag added to an image
     *
     * Add the tag to the box
     */
    initBboxTags (state, payload)
    {
        // reset the boxes
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
                            if (category === 'brands')
                            {
                                // add brand tag to another box
                                this.commit('addBoxBrand', tag);

                                i++;
                                continue;
                            }

                            this.commit('addNewBox');

                            // Get the last / most recently created box.id
                            const box_id = state.boxes[state.boxes.length -1].id;

                            // activate this box so we can add tags to it
                            // all other boxes will be deactivated
                            this.commit('activateBox', box_id);

                            // add category, tag to the active box
                            this.commit('addTagToBox', { category, tag });

                            // if (boxes.length < i + 1)
                            // {
                            //     this.commit('updateBoxPosition', {
                            //         width: boxes[i + 1].width,
                            //         height: boxes[i + 1].height,
                            //         top: boxes[i + 1].top,
                            //         left: boxes[i + 1].left,
                            //     });
                            // }

                            i++;
                        }
                    }
                });
            }
        });
    },

    /**
     * Move the active box up 1 pixel
     */
    moveBoxUp (state)
    {
        let boxes = [...state.boxes];

        boxes.map(box => {
            if (box.active)
            {
                box.top--;
            }

            return box;
        });

        state.boxes = boxes;
    },

    /**
     * Move the active box right 1 pixel
     */
    moveBoxRight (state)
    {
        let boxes = [...state.boxes];

        boxes.map(box => {
            if (box.active)
            {
                box.left++;
            }

            return box;
        });

        state.boxes = boxes;
    },

    /**
     * Move the active box down 1 pixel
     */
    moveBoxDown (state)
    {
        let boxes = [...state.boxes];

        boxes.map(box => {
            if (box.active)
            {
                box.top++;
            }

            return box;
        });

        state.boxes = boxes;
    },

    /**
     * Move the active box left 1 pixel
     */
    moveBoxLeft (state)
    {
        let boxes = [...state.boxes];

        boxes.map(box => {
            if (box.active)
            {
                box.left--;
            }

            return box;
        });

        state.boxes = boxes;
    },

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
     * Select 1 of many brands
     */
    selectBrandBoxIndex (state, payload)
    {
        state.selectedBrandIndex = payload;
    },

    /**
     *
     */
    toggleBoxLabel (state, payload)
    {
        let boxes = [...state.boxes];

        let box = boxes.find(box => box.id === payload);

        box.showLabel = ! box.showLabel;

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
                box.top = payload.top;
                box.left = payload.left;
                box.width = payload.width;
                box.height = payload.height;
            }

            return box;
        });
    },

    /**
     * When changing the tags of an image,
     *
     * We want to update to the previous box positions which were reset + cleared
     */
    updateBoxPositions (state, payload)
    {
        let boxes = [...state.boxes];

        boxes.map((box, index) => {

            if (payload[index])
            {
                box.top = payload[index].top;
                box.left = payload[index].left;
                box.width = payload[index].width;
                box.height = payload[index].height;
            }

            return box;
        });

        state.boxes = boxes;
    }
}
