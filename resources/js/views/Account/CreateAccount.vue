<template>
    <div class="relative min-h-[calc(100vh-80px)]">
        <!-- Background images with lower z-index -->
        <div class="absolute inset-0 z-0">
            <img
                :src="mountainsBg"
                alt=""
                class="h-full w-full object-cover pointer-events-none select-none md:hidden"
            />
            <img
                :src="mountainsWideBg"
                alt=""
                class="h-full w-full object-cover pointer-events-none select-none hidden md:block"
            />
            <div class="absolute inset-0 bg-gradient-to-b from-slate-900/70 via-blue-900/60 to-emerald-900/70"></div>
        </div>

        <!-- Content layer with higher z-index -->
        <div class="relative z-10 flex min-h-[calc(100vh-80px)] flex-col">
            <section class="flex-1 flex items-center justify-center flex-col p-4">
                <div class="w-full max-w-md">
                    <div class="rounded-xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-4 shadow-xl md:p-6">
                        <h2 class="mb-4 text-xl font-bold text-white">
                            {{ $t('Sign up to tell your story about litter & plastic pollution.') }}
                        </h2>

                        <!-- General server error -->
                        <p v-if="serverErrors.general" class="mb-3 rounded-lg bg-red-500/10 border border-red-500/20 p-3 text-sm text-red-400">
                            {{ serverErrors.general[0] }}
                        </p>

                        <form @submit.prevent="submit" novalidate>
                            <!-- Username -->
                            <div class="mb-3">
                                <label for="username" class="mb-1 block text-sm font-medium text-white/70">
                                    {{ $t('Username') }}
                                </label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                        :style="{ color: activeField === 'username' ? 'rgba(255,255,255,0.6)' : 'rgba(255,255,255,0.2)' }"
                                        aria-hidden="true"
                                    >
                                        <span class="text-base font-medium">@</span>
                                    </span>
                                    <input
                                        id="username"
                                        v-model.trim="username"
                                        @focus="activeField = 'username'"
                                        @blur="activeField = null"
                                        @input="onUsernameInput"
                                        type="text"
                                        placeholder="Type your own or hit refresh"
                                        autocomplete="username"
                                        :class="inputClass('username')"
                                        class="username-input w-full rounded-lg border pl-10 pr-10 py-2 focus:outline-none focus:ring-2"
                                    />
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2">
                                        <button
                                            type="button"
                                            @click="refreshUsername"
                                            class="p-1 text-white/30 hover:text-emerald-400 focus:outline-none transition-colors"
                                            aria-label="Generate new username"
                                            :title="$t('Generate new username')"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                                />
                                            </svg>
                                        </button>
                                    </span>
                                </div>
                                <p v-if="errorFor('username')" class="mt-1 text-xs text-red-400">
                                    {{ errorFor('username') }}
                                </p>
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="mb-1 block text-sm font-medium text-white/70">
                                    {{ $t('E-Mail Address') }}
                                </label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                        :style="{ color: activeField === 'email' ? 'rgba(255,255,255,0.6)' : 'rgba(255,255,255,0.2)' }"
                                        aria-hidden="true"
                                    >
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"
                                            />
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                        </svg>
                                    </span>
                                    <input
                                        id="email"
                                        v-model.trim="email"
                                        @focus="activeField = 'email'"
                                        @blur="
                                            validateField('email');
                                            activeField = null;
                                        "
                                        @input="clearError('email')"
                                        type="email"
                                        placeholder="you@email.com"
                                        autocomplete="email"
                                        :class="inputClass('email')"
                                        class="w-full rounded-lg border pl-10 pr-3 py-2 focus:outline-none focus:ring-2"
                                        required
                                    />
                                </div>
                                <p v-if="errorFor('email')" class="mt-1 text-xs text-red-400">
                                    {{ errorFor('email') }}
                                </p>
                            </div>

                            <!-- Password -->
                            <div class="mb-3">
                                <label for="password" class="mb-1 block text-sm font-medium text-white/70">
                                    {{ $t('Create a password') }}
                                </label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                        :style="{ color: activeField === 'password' ? 'rgba(255,255,255,0.6)' : 'rgba(255,255,255,0.2)' }"
                                        aria-hidden="true"
                                    >
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </span>

                                    <input
                                        id="password"
                                        v-model="password"
                                        @focus="activeField = 'password'"
                                        @blur="
                                            validateField('password');
                                            activeField = null;
                                        "
                                        @input="onPasswordInput"
                                        :type="showPassword ? 'text' : 'password'"
                                        placeholder="Your password (min 8 characters)"
                                        autocomplete="new-password"
                                        :class="inputClass('password')"
                                        class="w-full rounded-lg border pl-10 pr-10 py-2 focus:outline-none focus:ring-2"
                                        required
                                    />

                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="text-white/30 hover:text-white/60 focus:outline-none"
                                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        >
                                            <svg
                                                v-if="!showPassword"
                                                class="h-5 w-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                class="h-5 w-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                                                />
                                            </svg>
                                        </button>
                                    </span>
                                </div>

                                <p v-if="errorFor('password')" class="mt-1 text-xs text-red-400">
                                    {{ errorFor('password') }}
                                </p>
                            </div>

                            <!-- Terms -->
                            <div class="mb-3">
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input
                                        v-model="acceptedTerms"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded bg-white/5 border-white/20 text-emerald-500 focus:ring-emerald-500"
                                    />
                                    <span class="text-sm text-white/60">
                                        {{ $t('I agree to the') }}
                                        <router-link to="/terms" class="text-emerald-400 hover:underline"
                                            >{{ $t('Terms') }}</router-link
                                        >
                                        {{ $t('and') }}
                                        <router-link to="/privacy" class="text-emerald-400 hover:underline"
                                            >{{ $t('Privacy Policy') }}</router-link
                                        >
                                    </span>
                                </label>
                            </div>

                            <!-- reCAPTCHA -->
                            <div class="mb-3 flex justify-center">
                                <RecaptchaV2
                                    v-if="showRecaptcha"
                                    :sitekey="recaptchaSiteKey"
                                    @loadCallback="onRecaptchaVerify"
                                    @expiredCallback="onRecaptchaExpired"
                                    @errorCallback="onRecaptchaError"
                                />
                            </div>
                            <p
                                v-if="serverErrors['g-recaptcha-response']"
                                class="mb-2 text-center text-xs text-red-400"
                            >
                                {{ $t('Please complete the reCAPTCHA') }}
                            </p>

                            <!-- Submit -->
                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="w-full rounded-lg py-2.5 font-medium text-white transition-all disabled:cursor-not-allowed disabled:bg-white/10 disabled:text-white/30 bg-emerald-500 hover:bg-emerald-400"
                            >
                                <span v-if="!isSubmitting">{{ $t('Create Account') }}</span>
                                <span v-else class="flex items-center justify-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                        />
                                    </svg>
                                    {{ $t('Creating...') }}
                                </span>
                            </button>

                            <p class="mt-2 text-center text-xs text-white/30">
                                {{ $t("Check spam folder if verification email doesn't arrive") }}
                            </p>

                            <!-- Sign in link -->
                            <div class="mt-4 pt-4 border-t border-white/10">
                                <p class="text-center text-sm text-white/50">
                                    {{ $t('Have an account?') }}
                                    <button
                                        type="button"
                                        @click="navigateToLogin"
                                        class="text-emerald-400 hover:underline"
                                    >
                                        {{ $t('Sign in') }}
                                    </button>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useModalStore } from '@/stores/modal';
import { RecaptchaV2 } from 'vue3-recaptcha-v2';
import mountainsBg from '@/assets/pixel_art/mountains.JPG';
import mountainsWideBg from '@/assets/pixel_art/boy1.jpg';

const router = useRouter();
const toast = useToast();
const userStore = useUserStore();
const modalStore = useModalStore();

// Username generator — mirrors UsernameGeneratorService.php word lists
const ADJECTIVES = [
    'violently-enthusiastic', 'aggressively-hydrated', 'emotionally-overprepared',
    'deeply-unsettled', 'mildly-feral', 'chronically-committed',
    'irrationally-motivated', 'profoundly-bored', 'environmentally-triggered',
    'dangerously-caffeinated', 'excessively-alert', 'spiritually-activated',
    'socially-unnecessary', 'aggressively-helpful', 'suspiciously-vigilant',
    'inexplicably-determined', 'disturbingly-cheerful', 'unreasonably-passionate',
    'legally-distinct', 'professionally-nosy', 'catastrophically-invested',
    'excessively-wholesome', 'morally-exhausted', 'stubborn-beyond-reason',
    'permanently-busy', 'recreationally-serious', 'accidentally-legendary',
    'uncomfortably-efficient', 'wildly-overqualified', 'borderline-evangelical',
    'environmentally-unhinged', 'existentially-concerned', 'unlicensed',
    'lightly-supervised', 'unnecessarily-brave',
];

const NOUNS = [
    'bin-overlord', 'crumb-interrogator', 'bottle-whisperer', 'cap-harvester',
    'gum-evangelist', 'wrapper-warden', 'drain-lurker', 'crisp-packet-historian',
    'pavement-enforcer', 'roundabout-oracle', 'curb-tyrant', 'seagull-negotiator',
    'lid-archaeologist', 'debris-wrangler', 'litter-interventionist',
    'microplastic-detective', 'waste-summoner', 'compost-influencer',
    'drain-purifier', 'refuse-enthusiast', 'bottle-apologist', 'gum-hunter',
    'street-diplomat', 'plastic-prosecutor', 'crumb-collector-general',
    'wrapper-therapist', 'bin-strategist', 'cap-overseer', 'drain-warden',
    'civic-disruptor', 'litter-mystic', 'pavement-auditor', 'beach-activator',
    'rubbish-savant', 'field-chaos-coordinator',
];

function generateUsername() {
    const adj = ADJECTIVES[Math.floor(Math.random() * ADJECTIVES.length)];
    const noun = NOUNS[Math.floor(Math.random() * NOUNS.length)];
    const num = Math.floor(Math.random() * 9990) + 10;
    return `${adj}-${noun}-${num}`;
}

// Form fields
const username = ref(generateUsername());
const email = ref('');
const password = ref('');
const acceptedTerms = ref(false);
const g_recaptcha_response = ref('');

// UI state
const isSubmitting = ref(false);
const showPassword = ref(false);
const fieldErrors = ref({});
const activeField = ref(null);
const showRecaptcha = ref(false);

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || '6Le9FtwcAAAAAMOImuwEoOYssOVdNf7dfI2x8XZh';

// Server errors from store
const serverErrors = computed(() => userStore.errors || {});

/**
 * Return the first error for a field — client-side takes priority,
 * then fall back to the first server-side error string.
 */
function errorFor(field) {
    if (fieldErrors.value[field]) return fieldErrors.value[field];
    if (serverErrors.value[field]) {
        return Array.isArray(serverErrors.value[field]) ? serverErrors.value[field][0] : serverErrors.value[field];
    }
    return null;
}

const canSubmit = computed(() => {
    const basicRequirements = email.value && password.value && acceptedTerms.value && !isSubmitting.value;

    if (showRecaptcha.value) {
        return basicRequirements && g_recaptcha_response.value;
    }

    return basicRequirements;
});

function navigateToLogin() {
    if (modalStore && modalStore.showModal) {
        modalStore.showModal({
            type: 'Login',
            title: 'Login',
            showIcon: true,
        });
    } else {
        router.push('/');
    }
}

function validateField(field) {
    const newErrors = { ...fieldErrors.value };

    switch (field) {
        case 'email': {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value) {
                newErrors.email = 'Email is required';
            } else if (!emailRegex.test(email.value)) {
                newErrors.email = 'Please enter a valid email';
            } else {
                delete newErrors.email;
            }
            break;
        }

        case 'password':
            if (!password.value) {
                newErrors.password = 'Password is required';
            } else if (password.value.length < 8) {
                newErrors.password = 'Password must be at least 8 characters';
            } else {
                delete newErrors.password;
            }
            break;

    }

    fieldErrors.value = newErrors;
}

function clearError(field) {
    const newErrors = { ...fieldErrors.value };
    delete newErrors[field];
    fieldErrors.value = newErrors;

    userStore.clearError(field);
}

function onUsernameInput() {
    clearError('username');
}

function refreshUsername() {
    username.value = generateUsername();
    clearError('username');
}

function onPasswordInput() {
    clearError('password');
}

function inputClass(field) {
    if (errorFor(field)) {
        return 'bg-white/5 border-red-400/50 text-white placeholder-white/30 focus:border-red-400 focus:ring-red-400/30';
    }
    return 'bg-white/5 border-white/20 text-white placeholder-white/30 focus:border-emerald-500/50 focus:ring-emerald-500/30';
}

async function submit() {
    ['email', 'password'].forEach(validateField);

    if (Object.keys(fieldErrors.value).length > 0) {
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = {
            email: email.value,
            password: password.value,
            username: username.value || undefined,
        };

        if (g_recaptcha_response.value) {
            payload['g-recaptcha-response'] = g_recaptcha_response.value;
        }

        const result = await userStore.REGISTER(payload);

        if (result !== false) {
            toast.success('Welcome Aboard! Your Litter Mapping Adventure Awaits!', { timeout: 6000 });

            password.value = '';
            const intended = sessionStorage.getItem('intended_route');
            sessionStorage.removeItem('intended_route');
            await router.push(intended || '/upload');
        }
    } finally {
        isSubmitting.value = false;
    }
}

// reCAPTCHA handlers
function onRecaptchaVerify(token) {
    g_recaptcha_response.value = token || '';
}

function onRecaptchaExpired() {
    g_recaptcha_response.value = '';
}

function onRecaptchaError() {
    g_recaptcha_response.value = '';
    console.error('reCAPTCHA failed to load');
    showRecaptcha.value = false;
}

onUnmounted(() => {
    userStore.clearErrors();
});
</script>

<style scoped>
.relative {
    position: relative;
}

.z-0 {
    z-index: 0;
}

.z-10 {
    z-index: 10;
}

button:focus {
    outline: none;
}

a,
button {
    position: relative;
    z-index: 1;
}

.username-input {
    text-overflow: ellipsis;
}
</style>
