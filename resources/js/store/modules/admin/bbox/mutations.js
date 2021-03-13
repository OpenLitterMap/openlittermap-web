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

            console.log('activate', box.active);

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
            text: '',
            active: false
        });
    },

    /**
     * Add tag to a box
     *
     * 1 box = 1 tag?
     *
     * Payload = {
     *     category: {
     *         tag: quantity
     *     }
     * }
     *
     * eg { smoking: { butts: 1 } }
     */
    addTag (state, payload)
    {
        let boxes = [...state.boxes];

        let box = boxes.find(box => box.active);

        let tags = Object.assign({}, box.tags);

        tags = {
            ...tags,
            [payload.category]: {
                ...tags[payload.category],
                [payload.tag]: payload.quantity
            }
        };

        box.tags = tags;

        state.boxes = boxes;
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
