<template>
    <div v-if="open" class="export-drawer" :class="theme === 'dark' ? 'theme-dark' : 'theme-light'">
        <!-- Confirmation state -->
        <div v-if="queued" class="px-5 py-4 flex items-start gap-3" :class="confirmRowClass">
            <i class="fa fa-paper-plane mt-0.5" :class="theme === 'dark' ? 'text-emerald-400' : 'text-green-600'" />
            <div class="flex-1 text-sm leading-snug">
                <div :class="theme === 'dark' ? 'text-white' : 'text-gray-800'">
                    {{ $t('Queued — we\'ll email it to') }} <strong>{{ email }}</strong> {{ $t('when it\'s ready.') }}
                </div>
                <div class="mt-0.5 text-xs" :class="theme === 'dark' ? 'text-white/50' : 'text-gray-500'">
                    {{ $t('Usually under a minute. The link expires after 7 days.') }}
                </div>
            </div>
            <button @click="onCancel" class="text-xs underline" :class="theme === 'dark' ? 'text-white/60 hover:text-white' : 'text-gray-500 hover:text-gray-800'">
                {{ $t('Dismiss') }}
            </button>
        </div>

        <!-- Configuration state -->
        <div v-else class="px-5 py-4">
            <!-- Summary line -->
            <p class="text-xs mb-3" :class="theme === 'dark' ? 'text-white/60' : 'text-gray-600'">
                <span class="font-medium" :class="theme === 'dark' ? 'text-white/90' : 'text-gray-900'">{{ photoCountDisplay }}</span>
                {{ photoCount === 1 ? $t('photo matches your filters') : $t('photos match your filters') }}
            </p>

            <!-- Format radios -->
            <div class="space-y-2 mb-4">
                <label
                    v-for="opt in options"
                    :key="opt.key"
                    class="flex items-start gap-3 p-3 rounded cursor-pointer transition-colors"
                    :class="optionClass(opt.key)"
                >
                    <input
                        type="radio"
                        name="export-format"
                        :value="opt.key"
                        v-model="selectedKey"
                        class="mt-0.5"
                        :class="theme === 'dark' ? 'accent-emerald-500' : 'accent-blue-500'"
                    />
                    <div class="flex-1">
                        <div class="text-sm font-medium" :class="theme === 'dark' ? 'text-white' : 'text-gray-900'">
                            {{ $t(opt.title) }}
                        </div>
                        <div class="text-xs mt-0.5" :class="theme === 'dark' ? 'text-white/50' : 'text-gray-600'">
                            {{ $t(opt.desc) }}
                        </div>
                    </div>
                </label>
            </div>

            <!-- Actions row -->
            <div class="flex items-center justify-end gap-3">
                <button
                    @click="onCancel"
                    class="text-xs underline"
                    :class="theme === 'dark' ? 'text-white/60 hover:text-white' : 'text-gray-500 hover:text-gray-800'"
                >
                    {{ $t('Cancel') }}
                </button>
                <button
                    @click="onExport"
                    :disabled="exporting"
                    :class="primaryBtnClass"
                >
                    {{ exporting ? $t('Exporting...') : $t('Export CSV') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    scope: { type: String, required: true, validator: (v) => ['user', 'team', 'location'].includes(v) },
    scopeId: { type: [Number, String], default: null },
    photoCount: { type: Number, default: 0 },
    email: { type: String, default: '' },
    theme: { type: String, default: 'light', validator: (v) => ['light', 'dark'].includes(v) },
    open: { type: Boolean, default: false },
    queued: { type: Boolean, default: false },
    exporting: { type: Boolean, default: false },
});

const emit = defineEmits(['export', 'cancel']);

const options = [
    {
        key: 'excel',
        title: 'For Excel or Google Sheets',
        desc: 'One row per photo. Tag counts in columns.',
        layout: 'wide',
        format: 'split',
    },
    {
        key: 'analysis',
        title: 'For analysis tools (pandas, SQL, R, Tableau)',
        desc: 'One row per tag. Photo details repeat across rows.',
        layout: 'long',
        format: '',
    },
    {
        key: 'legacy',
        title: 'For legacy v4 scripts',
        desc: 'Object and type joined into one column (e.g. spirits_bottle).',
        layout: 'wide',
        format: 'joined',
    },
];

const selectedKey = ref('excel');

const photoCountDisplay = computed(() =>
    typeof props.photoCount === 'number' ? props.photoCount.toLocaleString() : props.photoCount
);

const confirmRowClass = computed(() =>
    props.theme === 'dark'
        ? 'bg-emerald-500/10 border-l-2 border-emerald-500/60'
        : 'bg-green-50 border-l-2 border-green-500'
);

const optionClass = (key) => {
    const active = selectedKey.value === key;
    if (props.theme === 'dark') {
        return active
            ? 'bg-emerald-500/10 border border-emerald-500/40'
            : 'bg-white/5 border border-white/10 hover:bg-white/10';
    }
    return active
        ? 'bg-blue-50 border border-blue-300'
        : 'bg-white border border-gray-200 hover:bg-gray-50';
};

const primaryBtnClass = computed(() =>
    props.theme === 'dark'
        ? 'px-4 py-1.5 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-medium rounded transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50'
        : 'px-4 py-1.5 bg-green-500 hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-medium rounded transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1'
);

const onExport = () => {
    const choice = options.find((o) => o.key === selectedKey.value) ?? options[0];
    emit('export', { layout: choice.layout, format: choice.format });
};

const onCancel = () => emit('cancel');
</script>

<style scoped>
.export-drawer {
    border-top: 1px solid var(--drawer-divider);
    border-bottom: 1px solid var(--drawer-divider);
}
.theme-light {
    --drawer-divider: #e5e7eb;
    background: #f9fafb;
}
.theme-dark {
    --drawer-divider: rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
}
</style>
