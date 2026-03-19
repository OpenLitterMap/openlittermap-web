<template>
    <div class="flex items-center justify-between gap-4">
        <label class="text-white/60 text-sm shrink-0 w-24">{{ label }}</label>
        <div class="flex-1 flex items-center gap-2">
            <input
                v-if="editing"
                ref="inputRef"
                v-model="editValue"
                :type="type"
                class="flex-1 bg-white/5 border border-white/20 rounded-lg px-3 py-1.5 text-white text-sm focus:border-emerald-500/50 focus:outline-none"
                @keyup.enter="save"
                @keyup.escape="cancel"
            />
            <span v-else class="flex-1 text-white text-sm truncate">{{ value || '—' }}</span>

            <button
                v-if="editing"
                class="text-emerald-400 text-sm hover:text-emerald-300 transition"
                @click="save"
            >
                Save
            </button>
            <button
                v-if="editing"
                class="text-white/40 text-sm hover:text-white/60 transition"
                @click="cancel"
            >
                Cancel
            </button>
            <button
                v-if="!editing"
                class="text-white/30 text-sm hover:text-white/50 transition"
                @click="startEdit"
            >
                Edit
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, default: '' },
    type: { type: String, default: 'text' },
});

const emit = defineEmits(['save']);

const editing = ref(false);
const editValue = ref('');
const inputRef = ref(null);

const startEdit = () => {
    editValue.value = props.value;
    editing.value = true;
    nextTick(() => inputRef.value?.focus());
};

const save = () => {
    if (editValue.value !== props.value) {
        emit('save', editValue.value);
    }
    editing.value = false;
};

const cancel = () => {
    editing.value = false;
};
</script>
