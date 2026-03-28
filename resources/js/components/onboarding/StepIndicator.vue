<template>
    <div class="flex items-center justify-center gap-2 sm:gap-4 py-4">
        <div
            v-for="(step, index) in steps"
            :key="index"
            class="flex items-center gap-2 sm:gap-4"
        >
            <!-- Step -->
            <div class="flex items-center gap-2">
                <!-- Circle -->
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold transition-all"
                    :class="stepCircleClass(index)"
                >
                    <!-- Checkmark for completed -->
                    <svg
                        v-if="isCompleted(index)"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="3"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    <span v-else>{{ index + 1 }}</span>
                </div>

                <!-- Label -->
                <span
                    class="hidden text-sm font-medium sm:block"
                    :class="stepLabelClass(index)"
                >
                    {{ step }}
                </span>
            </div>

            <!-- Connector -->
            <div
                v-if="index < steps.length - 1"
                class="h-0.5 w-6 sm:w-10"
                :class="isCompleted(index) ? 'bg-emerald-500' : 'bg-white/10'"
            />
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    currentStep: {
        type: Number,
        required: true,
        validator: (v) => v >= 1 && v <= 4,
    },
});

const steps = ['Upload a photo', 'Add tags', 'See your data'];

function isCompleted(index) {
    return index + 1 < props.currentStep;
}

function isActive(index) {
    return index + 1 === props.currentStep;
}

function stepCircleClass(index) {
    if (isCompleted(index)) {
        return 'border-emerald-500 bg-emerald-500 text-white';
    }
    if (isActive(index)) {
        return 'border-emerald-500 text-emerald-400 ring-2 ring-emerald-500/30';
    }
    return 'border-white/20 text-white/30';
}

function stepLabelClass(index) {
    if (isCompleted(index)) return 'text-emerald-400';
    if (isActive(index)) return 'text-white';
    return 'text-white/30';
}
</script>
