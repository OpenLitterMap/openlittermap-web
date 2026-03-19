<template>
    <tr class="border-b border-gray-700 hover:bg-gray-800/50 transition-colors">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <template v-if="editingUsername">
                    <input
                        ref="usernameInput"
                        v-model="newUsername"
                        @keyup.enter="saveUsername"
                        @keyup.escape="cancelEditUsername"
                        class="px-2 py-0.5 bg-gray-700 border border-gray-500 rounded text-sm text-white focus:outline-none focus:border-blue-500"
                    />
                    <button
                        @click="saveUsername"
                        :disabled="submitting"
                        class="text-xs text-green-400 hover:text-green-300 disabled:opacity-40"
                    >
                        Save
                    </button>
                    <button
                        @click="cancelEditUsername"
                        class="text-xs text-gray-400 hover:text-gray-300"
                    >
                        Cancel
                    </button>
                </template>
                <template v-else>
                    <span class="font-medium text-white">{{ user.username }}</span>
                    <span
                        v-if="user.username_flagged"
                        class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-amber-900/40 text-amber-400 border border-amber-700"
                    >
                        FLAGGED
                    </span>
                    <button
                        v-if="isSuperadmin"
                        @click="startEditUsername"
                        class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                        title="Edit username"
                    >
                        Edit
                    </button>
                </template>
            </div>
            <div v-if="user.name" class="text-xs text-gray-400">{{ user.name }}</div>
        </td>
        <td class="px-4 py-3 text-sm text-gray-300">{{ user.email }}</td>
        <td class="px-4 py-3 text-sm text-gray-400">{{ user.created_at }}</td>
        <td class="px-4 py-3 text-sm text-right text-gray-300">{{ user.photos_count }}</td>
        <td class="px-4 py-3 text-sm text-right text-yellow-400 font-mono">{{ user.xp.toLocaleString() }}</td>
        <td class="px-4 py-3 text-center">
            <button
                v-if="isSuperadmin"
                @click="handleTrustToggle"
                :disabled="submitting"
                :class="user.is_trusted
                    ? 'bg-green-900/40 text-green-400 border-green-700 hover:bg-green-900/60'
                    : 'bg-gray-800 text-gray-400 border-gray-600 hover:bg-gray-700'"
                class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full border cursor-pointer disabled:opacity-40 transition-colors"
            >
                {{ user.is_trusted ? 'Trusted' : 'Untrusted' }}
            </button>
            <span
                v-else
                :class="user.is_trusted
                    ? 'bg-green-900/40 text-green-400 border-green-700'
                    : 'bg-gray-800 text-gray-400 border-gray-600'"
                class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full border"
            >
                {{ user.is_trusted ? 'Trusted' : 'Untrusted' }}
            </span>
        </td>
        <td class="px-4 py-3 text-sm text-center">
            <div class="flex items-center justify-center gap-2">
                <span v-if="user.pending_photos > 0" class="text-amber-400 font-semibold">
                    {{ user.pending_photos }}
                </span>
                <span v-else class="text-gray-600">0</span>

                <button
                    v-if="isSuperadmin && user.pending_photos > 0"
                    @click="handleApproveAll"
                    :disabled="submitting"
                    class="px-2 py-0.5 text-xs bg-green-700 text-white rounded hover:bg-green-600 disabled:opacity-40 transition-colors"
                >
                    Approve All
                </button>
            </div>
        </td>
        <td class="px-4 py-3 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-500">{{ user.roles.join(', ') || 'user' }}</span>
                <button
                    v-if="isSuperadmin"
                    @click="handleToggleSchoolManager"
                    :disabled="submitting"
                    :class="isSchoolManager
                        ? 'bg-purple-900/40 text-purple-400 border-purple-700 hover:bg-purple-900/60'
                        : 'bg-gray-800 text-gray-500 border-gray-600 hover:text-white hover:bg-gray-700'"
                    class="px-2 py-0.5 text-[10px] font-medium rounded border cursor-pointer disabled:opacity-40 transition-colors"
                    :title="isSchoolManager ? 'Remove school manager role' : 'Grant school manager role'"
                >
                    {{ isSchoolManager ? 'School Manager' : '+ School' }}
                </button>
                <button
                    v-if="isSuperadmin && !isAdminUser"
                    @click="handleImpersonate"
                    :disabled="submitting"
                    class="px-2 py-0.5 text-[10px] font-medium rounded border bg-gray-800 text-amber-500 border-amber-700 hover:bg-amber-900/30 cursor-pointer disabled:opacity-40 transition-colors"
                    title="Login as this user"
                >
                    Login As
                </button>
            </div>
        </td>
    </tr>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';
import { useAdminStore } from '@stores/admin.js';
import { useUserStore } from '@stores/user/index.js';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['refresh']);

const adminStore = useAdminStore();
const userStore = useUserStore();

const submitting = computed(() => adminStore.submitting);

const editingUsername = ref(false);
const newUsername = ref('');
const usernameInput = ref(null);

const isSuperadmin = computed(() => {
    const roles = userStore.user?.roles || [];
    return roles.some((r) => r.name === 'superadmin');
});

const isSchoolManager = computed(() => props.user.roles.includes('school_manager'));
const isAdminUser = computed(() =>
    props.user.roles.some((r) => ['admin', 'superadmin'].includes(r))
);

const startEditUsername = async () => {
    newUsername.value = props.user.username;
    editingUsername.value = true;
    await nextTick();
    usernameInput.value?.focus();
};

const cancelEditUsername = () => {
    editingUsername.value = false;
    newUsername.value = '';
};

const saveUsername = async () => {
    if (!newUsername.value || newUsername.value === props.user.username) {
        cancelEditUsername();
        return;
    }

    const success = await adminStore.updateUsername(props.user.id, newUsername.value);
    if (success) {
        editingUsername.value = false;
        emit('refresh');
    }
};

const handleTrustToggle = async () => {
    const newTrusted = !props.user.is_trusted;
    const action = newTrusted ? 'trust' : 'untrust';
    if (!window.confirm(`${newTrusted ? 'Trust' : 'Untrust'} this user? ${newTrusted ? 'Their future uploads will be auto-approved.' : 'Their future uploads will require admin review.'}`)) {
        return;
    }

    const success = await adminStore.toggleTrust(props.user.id, newTrusted);
    if (success) {
        emit('refresh');
    }
};

const handleToggleSchoolManager = async () => {
    const enabling = !isSchoolManager.value;
    const action = enabling ? 'Grant school manager role to' : 'Remove school manager role from';
    if (!window.confirm(`${action} ${props.user.username}?`)) {
        return;
    }

    const success = await adminStore.toggleSchoolManager(props.user.id, enabling);
    if (success) {
        emit('refresh');
    }
};

const handleImpersonate = async () => {
    if (!window.confirm(`Login as ${props.user.username}? You will be redirected to the homepage as this user.`)) {
        return;
    }

    await adminStore.impersonateUser(props.user.id);
};

const handleApproveAll = async () => {
    if (!window.confirm(`Approve all ${props.user.pending_photos} pending photos for ${props.user.username}?`)) {
        return;
    }

    const result = await adminStore.approveAllForUser(props.user.id);
    if (result) {
        emit('refresh');
    }
};
</script>
