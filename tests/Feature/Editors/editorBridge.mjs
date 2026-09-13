import { readFileSync } from 'node:fs';
import {
    cardsFromApiTags,
    buildSearchableTags,
    cardFromSelection,
    cardFromCustomTag,
    setCardType,
    addCardDetail,
    removeCardDetail,
    hasUnresolvedCards,
    payloadFromCards,
    setCardQuantity,
} from '../../../resources/js/composables/useTagEditorState.js';
import { calculateTotalXp } from '../../../resources/js/views/General/Tagging/v2/useXpCalculator.js';
import { mutations } from '../../../resources/js/stores/tags/mutations.js';
const input = JSON.parse(readFileSync(0, 'utf8'));
const tagsStore = {};
mutations.initAllTags.call(tagsStore, input.catalogue ?? {});
tagsStore.getCloId = (category, object) =>
    tagsStore.categoryObjects.find((clo) => clo.category_id === category && clo.litter_object_id === object)?.id ??
    null;
const searchable = buildSearchableTags(tagsStore);
const cards = input.cards ?? cardsFromApiTags(input.photo?.new_tags ?? [], tagsStore);
for (const action of input.actions ?? []) {
    const card = cards[action.card ?? 0];
    switch (action.kind) {
        case 'select': {
            const selected = searchable.find((tag) => tag.id === action.id);
            if (!selected) throw new Error(`Picker choice missing: ${action.id}`);
            cards.push(cardFromSelection(selected, tagsStore, action.pickedUp));
            break;
        }
        case 'custom':
            cards.push(cardFromCustomTag({ key: action.key }));
            break;
        case 'quantity':
            setCardQuantity(card, action.value);
            break;
        case 'type':
            setCardType(card, action.value, tagsStore);
            break;
        case 'pickedUp':
            card.pickedUp = action.value;
            break;
        case 'addDetail':
            addCardDetail(card, action.detail);
            break;
        case 'removeDetail':
            removeCardDetail(card, action.detail);
            break;
        default:
            throw new Error(`Unknown editor action: ${action.kind}`);
    }
}
if (input.edit) setCardQuantity(cards[0], input.edit.quantity);
process.stdout.write(
    JSON.stringify({
        cards,
        payload: payloadFromCards(cards),
        xp: calculateTotalXp(cards),
        unresolved: hasUnresolvedCards(cards),
        searchIds: searchable.map((tag) => tag.id),
    })
);
