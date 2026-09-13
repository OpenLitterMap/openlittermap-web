import { readFileSync } from 'node:fs';
import {
    cardsFromApiTags,
    hasUnresolvedCards,
    payloadFromCards,
    setCardQuantity,
} from '../../../resources/js/composables/useTagEditorState.js';
import { calculateTotalXp } from '../../../resources/js/views/General/Tagging/v2/useXpCalculator.js';
const input = JSON.parse(readFileSync(0, 'utf8'));
const cards =
    input.cards ??
    cardsFromApiTags(input.photo.new_tags, {
        types: input.types,
        getCloId: (category, object) => input.cloMap?.[`${category}:${object}`] ?? null,
    });
if (input.edit) setCardQuantity(cards[0], input.edit.quantity);
process.stdout.write(
    JSON.stringify({
        cards,
        payload: payloadFromCards(cards),
        xp: calculateTotalXp(cards),
        unresolved: hasUnresolvedCards(cards),
    })
);
