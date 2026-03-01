<template>
    <div class="max-w-lg">
        <!-- No remaining teams and not a school manager -->
        <div v-if="remaining <= 0 && !isSchoolManager" class="bg-blue-50 border border-blue-200 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-blue-900 mb-2">{{ $t('Want to set up a team?') }}</h2>
            <p class="text-blue-800 text-sm">
                {{ $t("Contact us to set up a team for your school. We'll assign you as a school manager so you can create and manage your team.") }}
            </p>
            <a
                href="/contact-us"
                class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
            >
                {{ $t('Contact Us') }}
            </a>
        </div>

        <!-- Has quota -->
        <template v-else-if="remaining > 0">
            <h1 class="text-2xl font-bold text-slate-800 mb-6">{{ $t('Create a Team') }}</h1>

            <form class="bg-white rounded-xl p-6 shadow-sm space-y-5" @submit.prevent="submit">
                <!-- Team Type -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('Team type') }}</label>
                    <select
                        v-model="teamType"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                    >
                        <option v-for="t in availableTypes" :key="t.id" :value="t.id">{{ t.team }}</option>
                    </select>
                </div>

                <!-- Team Name -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('Team name') }}</label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        placeholder="My Awesome Team"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                        :class="errors.name ? 'border-red-400' : ''"
                        @input="teamsStore.clearError('name')"
                    />
                    <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
                </div>

                <!-- Identifier -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('Unique team ID') }}</label>
                    <p class="text-xs text-slate-400 mb-1">{{ $t('Share this code so others can join your team.') }}</p>
                    <input
                        v-model="identifier"
                        type="text"
                        required
                        placeholder="Awesome2026"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                        :class="errors.identifier ? 'border-red-400' : ''"
                        @input="teamsStore.clearError('identifier')"
                    />
                    <p v-if="errors.identifier" class="text-red-500 text-xs mt-1">{{ errors.identifier[0] }}</p>
                </div>

                <!-- School-specific fields -->
                <template v-if="isSchoolType">
                    <!-- School Logo -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('School logo') }}</label>
                        <div
                            class="relative border-2 border-dashed border-slate-300 rounded-lg p-4 text-center hover:border-blue-400 transition-colors cursor-pointer"
                            :class="errors.logo ? 'border-red-400' : ''"
                            @click="logoInput?.click()"
                            @dragover.prevent
                            @drop.prevent="handleLogoDrop"
                        >
                            <img
                                v-if="logoPreview"
                                :src="logoPreview"
                                class="mx-auto max-h-24 mb-2 rounded"
                                alt="Logo preview"
                            />
                            <template v-else>
                                <p class="text-slate-400 text-sm">{{ $t('Click or drag to upload logo') }}</p>
                                <p class="text-slate-300 text-xs mt-1">PNG, JPG — max 2MB</p>
                            </template>
                            <button
                                v-if="logoPreview"
                                type="button"
                                class="text-xs text-red-400 hover:text-red-500 mt-1"
                                @click.stop="clearLogo"
                            >
                                {{ $t('Remove') }}
                            </button>
                        </div>
                        <input
                            ref="logoInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleLogoSelect"
                        />
                        <p v-if="errors.logo" class="text-red-500 text-xs mt-1">{{ errors.logo[0] }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('Contact email') }}</label>
                        <input
                            v-model="contactEmail"
                            type="email"
                            required
                            placeholder="teacher@school.ie"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            :class="errors.contact_email ? 'border-red-400' : ''"
                            @input="teamsStore.clearError('contact_email')"
                        />
                        <p v-if="errors.contact_email" class="text-red-500 text-xs mt-1">{{ errors.contact_email[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('County') }}</label>
                        <input
                            v-model="county"
                            type="text"
                            placeholder="Roscommon"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('Academic year') }}</label>
                            <input
                                v-model="academicYear"
                                type="text"
                                placeholder="2025/2026"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('Class group') }}</label>
                            <input
                                v-model="classGroup"
                                type="text"
                                placeholder="5th Class"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('Max participants') }}</label>
                        <p class="text-xs text-slate-400 mb-1">{{ $t('Maximum number of students who can join this team.') }}</p>
                        <input
                            v-model.number="maxParticipants"
                            type="number"
                            min="1"
                            max="500"
                            placeholder="30"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                            :class="errors.max_participants ? 'border-red-400' : ''"
                            @input="teamsStore.clearError('max_participants')"
                        />
                        <p v-if="errors.max_participants" class="text-red-500 text-xs mt-1">{{ errors.max_participants[0] }}</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <input
                            id="participant_sessions"
                            v-model="participantSessionsEnabled"
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <div>
                            <label for="participant_sessions" class="block text-sm font-medium text-slate-700">
                                {{ $t('Enable participant sessions') }}
                            </label>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ $t('Students join with session codes instead of creating accounts. Photos are owned by the facilitator.') }}
                            </p>
                        </div>
                    </div>
                </template>

                <button
                    type="submit"
                    :disabled="processing"
                    class="w-full py-2 rounded-lg text-white font-medium text-sm transition-colors"
                    :class="processing ? 'bg-slate-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                >
                    {{ processing ? $t('Creating...') : $t('Create Team') }}
                </button>
            </form>
        </template>

        <!-- School manager with no remaining teams -->
        <div v-else class="bg-amber-50 border border-amber-200 rounded-xl p-6">
            <p class="text-amber-800">{{ $t("You've reached the maximum number of teams you can create.") }}</p>
            <p class="text-amber-700 text-sm mt-2">
                {{ $t('Need another team?') }}
                <a href="/contact-us" class="underline hover:text-amber-900">{{ $t('Contact us') }}</a>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { useTeamsStore } from '@/stores/teams';
import { useUserStore } from '@/stores/user';

const toast = useToast();
const teamsStore = useTeamsStore();
const userStore = useUserStore();

const name = ref('');
const identifier = ref('');
const teamType = ref(null);
const contactEmail = ref('');
const county = ref('');
const academicYear = ref('');
const classGroup = ref('');
const maxParticipants = ref(null);
const participantSessionsEnabled = ref(false);
const logoFile = ref(null);
const logoPreview = ref(null);
const logoInput = ref(null);
const processing = ref(false);

const errors = computed(() => teamsStore.errors);
const remaining = computed(() => userStore.user?.remaining_teams ?? 0);
const isSchoolManager = computed(() => {
    const roles = userStore.user?.roles || [];
    return roles.some((r) => r.name === 'school_manager');
});

// Filter team types: school types only visible to school_managers
const availableTypes = computed(() => {
    const allTypes = teamsStore.types || [];
    if (isSchoolManager.value) {
        return allTypes;
    }
    return allTypes.filter((t) => t.team !== 'school');
});

const isSchoolType = computed(() => {
    const types = teamsStore.types || [];
    const selected = types.find((t) => t.id === teamType.value);
    return selected?.team === 'school';
});

const handleLogoSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
        setLogo(file);
    }
};

const handleLogoDrop = (e) => {
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        setLogo(file);
    }
};

const setLogo = (file) => {
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoFile.value = file;
    logoPreview.value = URL.createObjectURL(file);
};

const clearLogo = () => {
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoFile.value = null;
    logoPreview.value = null;
};

const emit = defineEmits(['navigate']);

const submit = async () => {
    processing.value = true;

    const payload = {
        name: name.value,
        identifier: identifier.value,
        teamType: teamType.value,
    };

    if (isSchoolType.value) {
        payload.contact_email = contactEmail.value;
        payload.county = county.value;
        payload.academic_year = academicYear.value;
        payload.class_group = classGroup.value;
        if (maxParticipants.value) {
            payload.max_participants = maxParticipants.value;
        }
        if (logoFile.value) {
            payload.logo = logoFile.value;
        }
        payload.participant_sessions_enabled = participantSessionsEnabled.value;
    }

    const team = await teamsStore.createTeam(payload);

    processing.value = false;

    if (team) {
        toast.success(`Team "${team.name}" created!`);
        emit('navigate', 'dashboard');
    }
};

// Set initial type once types are loaded
watch(availableTypes, (types) => {
    if (types.length && !teamType.value) {
        teamType.value = types[0].id;
    }
}, { immediate: true });

// Clear errors on mount
teamsStore.clearErrors();
</script>
