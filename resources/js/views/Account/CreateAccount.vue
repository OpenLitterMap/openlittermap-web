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
            <!-- Optional subtle vignette -->
            <div class="absolute inset-0 bg-gradient-to-b from-black/0 via-black/0 to-black/15"></div>
        </div>

        <!-- Content layer with higher z-index -->
        <div class="relative z-10 flex min-h-[calc(100vh-80px)] flex-col">
            <!-- Form Section -->
            <section class="flex-1 flex items-center justify-center flex-col p-4">
                <div class="w-full max-w-md">
                    <div class="rounded-xl bg-white p-4 shadow-xl md:p-6">
                        <h2 class="mb-4 text-xl font-bold text-gray-900">
                            Sign up to tell your story about litter & plastic pollution.
                        </h2>

                        <form @submit.prevent="submit" novalidate>
                            <!-- Username -->
                            <div class="mb-3">
                                <label for="username" class="mb-1 block text-sm font-medium text-gray-700">
                                    Username
                                </label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                        :style="{ color: activeField === 'username' ? '#4a4a4a' : '#dbdbdb' }"
                                        aria-hidden="true"
                                    >
                                        <span class="text-base font-medium">@</span>
                                    </span>
                                    <input
                                        id="username"
                                        v-model.trim="username"
                                        @focus="activeField = 'username'"
                                        @blur="
                                            validateField('username');
                                            activeField = null;
                                        "
                                        @input="clearError('username')"
                                        type="text"
                                        placeholder="LitterNinja"
                                        :class="inputClass('username')"
                                        class="w-full rounded-lg border pl-10 pr-3 py-2 focus:outline-none focus:ring-2"
                                        required
                                    />
                                </div>
                                <p v-if="fieldErrors.username" class="mt-1 text-xs text-red-600">
                                    {{ fieldErrors.username }}
                                </p>
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="mb-1 block text-sm font-medium text-gray-700">
                                    E-Mail Address
                                </label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                        :style="{ color: activeField === 'email' ? '#4a4a4a' : '#dbdbdb' }"
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
                                <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">
                                    {{ fieldErrors.email }}
                                </p>
                            </div>

                            <!-- Password -->
                            <div class="mb-3">
                                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">
                                    Create a password
                                </label>
                                <div class="relative">
                                    <!-- Left icon: vertically centered via inset-y + flex -->
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                        :style="{ color: activeField === 'password' ? '#4a4a4a' : '#dbdbdb' }"
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
                                        placeholder="Your password (min 5 characters)"
                                        autocomplete="new-password"
                                        :class="inputClass('password')"
                                        class="w-full rounded-lg border pl-10 pr-10 py-2 focus:outline-none focus:ring-2"
                                        required
                                    />

                                    <!-- Right toggle button: also vertically centered -->
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="text-gray-500 hover:text-gray-700 focus:outline-none"
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

                                <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-600">
                                    {{ fieldErrors.password }}
                                </p>
                            </div>

                            <!-- Terms -->
                            <div class="mb-3">
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input
                                        v-model="acceptedTerms"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-green-600"
                                    />
                                    <span class="text-sm text-gray-600">
                                        I agree to the
                                        <router-link to="/terms" class="text-green-600 hover:underline"
                                            >Terms</router-link
                                        >
                                        and
                                        <router-link to="/privacy" class="text-green-600 hover:underline"
                                            >Privacy Policy</router-link
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
                            <p v-if="errors['g-recaptcha-response']" class="mb-2 text-center text-xs text-red-600">
                                Please complete the reCAPTCHA
                            </p>

                            <!-- Submit -->
                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="w-full rounded-lg bg-green-600 py-2.5 font-medium text-white transition-all hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                            >
                                <span v-if="!isSubmitting">Create Account</span>
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
                                    Creating...
                                </span>
                            </button>

                            <p class="mt-2 text-center text-xs text-gray-500">
                                Check spam folder if verification email doesn't arrive
                            </p>

                            <!-- Sign in link moved inside form -->
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <p class="text-center text-sm text-gray-600">
                                    Have an account?
                                    <button
                                        type="button"
                                        @click="navigateToLogin"
                                        class="text-gray-900 hover:underline"
                                    >
                                        Sign in
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
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { usePlansStore } from '@/stores/plans';
import { useModalStore } from '@/stores/modal';
import { RecaptchaV2 } from 'vue3-recaptcha-v2';
import mountainsBg from '@/assets/pixel_art/mountains.JPG';
import mountainsWideBg from '@/assets/pixel_art/boy1.jpg';

const router = useRouter();
const plansStore = usePlansStore();
const modalStore = useModalStore();

// Form fields
const username = ref('');
const email = ref('');
const password = ref('');
const acceptedTerms = ref(false);
const g_recaptcha_response = ref('');

// UI state
const isSubmitting = ref(false);
const showPassword = ref(false);
const fieldErrors = ref({});
const activeField = ref(null);
const showRecaptcha = ref(false); // Start with false, enable when component mounts

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || '6Le9FtwcAAAAAMOImuwEoOYssOVdNf7dfI2x8XZh';

// Server errors from store
const errors = computed(() => plansStore.errors || {});

const canSubmit = computed(() => {
    // Check if basic form requirements are met
    const basicRequirements =
        username.value && email.value && password.value && acceptedTerms.value && !isSubmitting.value;

    // Only require recaptcha if it's shown and loaded
    if (showRecaptcha.value) {
        return basicRequirements && g_recaptcha_response.value;
    }

    return basicRequirements;
});

// Navigation function for login
function navigateToLogin() {
    // Try modal first
    if (modalStore && modalStore.showModal) {
        modalStore.showModal({
            type: 'Login',
            title: 'Login',
            showIcon: true,
        });
    } else {
        // Fallback to root route if modal store isn't available
        router.push('/');
    }
}

// Field validation
function validateField(field) {
    const newErrors = { ...fieldErrors.value };

    switch (field) {
        case 'username':
            if (!username.value) {
                newErrors.username = 'Username is required';
            } else if (username.value.length < 3) {
                newErrors.username = 'Username must be at least 3 characters';
            } else if (!/^[a-zA-Z0-9_-]+$/.test(username.value)) {
                newErrors.username = 'Username can only contain letters, numbers, - and _';
            } else {
                delete newErrors.username;
            }
            break;

        case 'email':
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value) {
                newErrors.email = 'Email is required';
            } else if (!emailRegex.test(email.value)) {
                newErrors.email = 'Please enter a valid email';
            } else {
                delete newErrors.email;
            }
            break;

        case 'password':
            if (!password.value) {
                newErrors.password = 'Password is required';
            } else if (password.value.length < 5) {
                newErrors.password = 'Password must be at least 5 characters';
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

    // Clear server errors if plansStore has the method
    if (errors.value?.[field] && plansStore.clearError) {
        plansStore.clearError(field);
    }
}

function onPasswordInput() {
    clearError('password');
}

function inputClass(field) {
    const hasLocalError = fieldErrors.value[field];
    const hasServerError = errors.value?.[field];

    if (hasLocalError || hasServerError) {
        return 'border-red-300 focus:border-red-400 focus:ring-red-200';
    }
    return 'border-gray-300 focus:border-green-400 focus:ring-green-200';
}

async function submit() {
    console.log('Submit clicked. Form state:', {
        username: username.value,
        email: email.value,
        password: password.value ? 'set' : 'not set',
        acceptedTerms: acceptedTerms.value,
        recaptcha: g_recaptcha_response.value ? 'set' : 'not set',
        canSubmit: canSubmit.value,
    });

    // Validate all fields
    ['username', 'email', 'password'].forEach(validateField);

    if (Object.keys(fieldErrors.value).length > 0) {
        console.log('Validation errors:', fieldErrors.value);
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = {
            username: username.value,
            email: email.value,
            password: password.value,
            password_confirmation: password.value,
            'g-recaptcha-response': g_recaptcha_response.value || 'bypass',
            plan: 1,
            plan_id: null,
        };

        const result = await plansStore.createAccount(payload);

        // Clear sensitive data on success
        password.value = '';

        // Navigate to home after successful signup
        // Check if result indicates success
        if (result !== false) {
            console.log('Account created successfully, navigating to home');
            await router.push('/');
        }
    } catch (error) {
        console.error('Signup error:', error);
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
    // Optionally hide recaptcha if it fails to load
    console.error('reCAPTCHA failed to load');
    showRecaptcha.value = false;
}

// Clean up on unmount
onUnmounted(() => {
    // Clear any errors when leaving the page
    if (plansStore.clearErrors) {
        plansStore.clearErrors();
    }
});

onMounted(async () => {
    // Only fetch plans if the method exists
    if (plansStore.fetchPlans) {
        try {
            await plansStore.fetchPlans();
        } catch (error) {
            console.error('Failed to fetch plans:', error);
        }
    }

    // Enable recaptcha after mount to avoid loading issues
    // You can set this to true if you want to try loading recaptcha
    // showRecaptcha.value = true;

    // For debugging: log when validation state changes
    console.log('Form mounted. Initial canSubmit:', canSubmit.value);
});
</script>

<style scoped>
/* Ensure the form is always accessible and above background */
.relative {
    position: relative;
}

/* Ensure z-index layering works properly */
.z-0 {
    z-index: 0;
}

.z-10 {
    z-index: 10;
}

/* Prevent any overflow issues */
button:focus {
    outline: none;
}

/* Ensure router-links work properly */
a,
button {
    position: relative;
    z-index: 1;
}
</style>
