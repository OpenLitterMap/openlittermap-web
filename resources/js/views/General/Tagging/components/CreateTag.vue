<template>
    <div class="mb-4">
        <Combobox :value="inputValue" @update:modelValue="noop">
            <ComboboxInput
                :displayValue="() => inputValue"
                @input="handleInput"
                :placeholder="placeholder"
                @keyup.enter="submitTag"
                @blur="submitTag"
                class="capitalize rounded-md border border-gray-300 bg-white py-2 px-3 text-left shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                :style="size === 'small' ? 'height: 35px' : ''"
            />
        </Combobox>
    </div>
</template>

<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';
import { Combobox, ComboboxInput } from '@headlessui/vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Enter custom tag',
    },
    size: {
        type: String,
        default: 'normal', // "normal" or "small"
    },
});

const emit = defineEmits(['createTag']);

const inputValue = ref(props.modelValue);

watch(
    () => props.modelValue,
    (newVal) => {
        inputValue.value = newVal;
    }
);

function handleInput(e) {
    inputValue.value = e.target.value;
}

let hasSubmitted = false;
function submitTag(e) {
    if (hasSubmitted) return;
    hasSubmitted = true;

    const trimmed = inputValue.value.trim();
    if (trimmed) {
        const tag = {
            id: Date.now(),
            key: trimmed,
            text: trimmed,
        };

        emit('createTag', tag);
    }
    inputValue.value = '';
    // Reset the flag on next tick so subsequent submissions work.
    setTimeout(() => {
        hasSubmitted = false;
    }, 0);
}

function noop() {}
</script>

<style scoped></style>
