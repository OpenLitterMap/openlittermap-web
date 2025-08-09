<template>
    <div class="min-h-screen">
        <!-- Hero Section - Full Width Blue Gradient -->
        <section class="relative overflow-hidden bg-gradient-to-br from-[#3273dc] via-[#4582e6] to-[#5a91f0]">
            <!-- Decorative background elements -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent"></div>
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-blue-300/20 blur-3xl"></div>

            <div class="relative px-4 py-32 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-4xl text-center">
                    <h1 class="mb-4 text-4xl font-bold text-white drop-shadow-lg md:text-5xl">Are you ready?</h1>
                    <p class="text-lg text-white/95 drop-shadow md:text-xl">Become an expert litter mapper.</p>
                </div>
            </div>
        </section>

        <!-- Main Content - Grey/Slate Background -->
        <section class="relative bg-gradient-to-b from-slate-200 via-slate-300 to-slate-400 px-4 pb-16 pt-10">
            <!-- Form Card Container - Overlapping Hero -->
            <div class="mx-auto max-w-2xl">
                <!-- Main Form Card -->
                <div class="rounded-2xl bg-white p-8 shadow-xl">
                    <h2 class="mb-8 text-center text-3xl font-bold text-slate-800">Create your account</h2>

                    <form @submit.prevent="submit" @keydown="clearError($event.target?.name)">
                        <!-- Name Field -->
                        <div class="mb-6">
                            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700"> Name </label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-5 w-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <input
                                    id="name"
                                    name="name"
                                    type="text"
                                    autocomplete="name"
                                    placeholder="Your full name"
                                    v-model.trim="name"
                                    class="w-full rounded-lg border py-3 pl-10 pr-4 transition-colors focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    :class="hasError('name') ? 'border-red-300 bg-red-50' : 'border-slate-300'"
                                    required
                                />
                            </div>
                            <p v-if="hasError('name')" class="mt-1 text-sm text-red-600">{{ firstError('name') }}</p>
                        </div>

                        <!-- Username Field -->
                        <div class="mb-6">
                            <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">
                                Unique Identifier
                            </label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 font-medium"
                                >
                                    @
                                </span>
                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    placeholder="Unique Username or Organisation"
                                    v-model.trim="username"
                                    class="w-full rounded-lg border py-3 pl-8 pr-4 transition-colors focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    :class="hasError('username') ? 'border-red-300 bg-red-50' : 'border-slate-300'"
                                    required
                                />
                            </div>
                            <p v-if="hasError('username')" class="mt-1 text-sm text-red-600">
                                {{ firstError('username') }}
                            </p>
                        </div>

                        <!-- Email Field -->
                        <div class="mb-6">
                            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">
                                E-Mail Address
                            </label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-5 w-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"
                                        />
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                </div>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="you@email.com"
                                    v-model.trim="email"
                                    class="w-full rounded-lg border py-3 pl-10 pr-4 transition-colors focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    :class="hasError('email') ? 'border-red-300 bg-red-50' : 'border-slate-300'"
                                    required
                                />
                            </div>
                            <p v-if="hasError('email')" class="mt-1 text-sm text-red-600">{{ firstError('email') }}</p>
                        </div>

                        <!-- Password Field -->
                        <div class="mb-6">
                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">
                                Create a strong password
                            </label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-5 w-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Create a strong password"
                                    v-model="password"
                                    class="w-full rounded-lg border py-3 pl-10 pr-4 transition-colors focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    :class="hasError('password') ? 'border-red-300 bg-red-50' : 'border-slate-300'"
                                    required
                                />
                            </div>
                            <p v-if="hasError('password')" class="mt-1 text-sm text-red-600">
                                {{ firstError('password') }}
                            </p>
                        </div>

                        <!-- Terms Checkbox -->
                        <div class="mb-6">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input
                                    id="ConfirmToS"
                                    name="ConfirmToS"
                                    type="checkbox"
                                    v-model="acceptedTerms"
                                    class="mt-0.5 h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-slate-600">
                                    I have read and agree to the
                                    <a href="/terms" class="text-blue-600 hover:text-blue-700 underline"
                                        >Terms and Conditions of use</a
                                    >
                                    and
                                    <a href="/privacy" class="text-blue-600 hover:text-blue-700 underline"
                                        >Privacy Policy</a
                                    >
                                </span>
                            </label>
                        </div>

                        <!-- reCAPTCHA -->
                        <div class="mb-6 flex justify-center">
                            <RecaptchaV2
                                :sitekey="recaptchaSiteKey"
                                @loadCallback="onRecaptchaVerify"
                                @expiredCallback="onRecaptchaExpired"
                                @errorCallback="onRecaptchaError"
                            />
                        </div>
                        <p v-if="hasError('g-recaptcha-response')" class="mb-4 text-center text-sm text-red-600">
                            {{ firstError('g-recaptcha-response') }}
                        </p>

                        <!-- Submit Button -->
                        <div class="text-center">
                            <button
                                type="submit"
                                :disabled="isSubmitting || !acceptedTerms"
                                class="mb-4 rounded-lg bg-gradient-to-r from-blue-500 to-cyan-500 px-8 py-3 text-lg font-semibold text-white shadow-lg transition-all hover:from-blue-600 hover:to-cyan-600 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span v-if="!isSubmitting">Sign up</span>
                                <span v-else class="flex items-center gap-2">
                                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
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
                                    Creating account...
                                </span>
                            </button>

                            <p class="text-sm text-slate-600">
                                Note: If you do not receive the verification e-mail in your inbox, please check your
                                spam/junk folder.
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Sign In Link -->
                <div class="mt-6 text-center">
                    <p class="text-slate-600">
                        Already have an account?
                        <a href="/login" class="font-semibold text-blue-600 hover:text-blue-700 underline"
                            >Sign in here</a
                        >
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { usePlansStore } from '@/stores/plans';
import { RecaptchaV2 } from 'vue3-recaptcha-v2';

const props = defineProps({
    plan: { type: String, required: false },
});

const router = useRouter();
const plansStore = usePlansStore();

// Form state
const name = ref('');
const username = ref('');
const email = ref('');
const password = ref('');
const password_confirmation = ref('');
const acceptedTerms = ref(false);
const g_recaptcha_response = ref('');

const isSubmitting = ref(false);
const planInt = ref(1);

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || '6Le9FtwcAAAAAMOImuwEoOYssOVdNf7dfI2x8XZh';

const plans = computed(() => plansStore.plans);
const errors = computed(() => plansStore.errors);

function hasError(key) {
    return Boolean(errors.value?.[key] && errors.value[key].length);
}

function firstError(key) {
    return hasError(key) ? errors.value[key][0] : '';
}

function clearError(key) {
    if (!key) return;
    if (errors.value?.[key]) plansStore.clearError(key);
}

function planNameToId(s) {
    const idx = plans.value.find((p) => p.name.toLowerCase() === String(s || '').toLowerCase());
    return idx ? idx.id : 1;
}

async function submit() {
    if (!acceptedTerms.value) {
        alert('Please accept the terms and conditions to continue.');
        return;
    }

    isSubmitting.value = true;

    const selected = plans.value.find((p) => p.id === planInt.value);
    const plan_id = selected?.plan_id ?? null;

    try {
        await plansStore.createAccount({
            name: name.value,
            username: username.value,
            email: email.value,
            password: password.value,
            password_confirmation: password_confirmation.value,
            'g-recaptcha-response': g_recaptcha_response.value,
            plan: planInt.value,
            plan_id,
        });

        password_confirmation.value = '';
    } finally {
        isSubmitting.value = false;
    }
}

function onPlanChanged(e) {
    const id = Number(e?.target?.value ?? planInt.value);
    const p = plans.value.find((pl) => pl.id === id);
    if (!p) return;
    router.push({ path: '/join', query: { plan: p.name.toLowerCase() } });
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
}

onMounted(async () => {
    if (!plans.value.length) await plansStore.fetchPlans();

    const qp = router.currentRoute.value.query.plan || props.plan;
    if (qp) planInt.value = planNameToId(qp);
});
</script>
