import { useUserStore } from '@/stores/user';

export default function onboarding({ to, next }) {
    const userStore = useUserStore();

    if (userStore.auth && !userStore.onboardingCompleted) {
        return next({ name: 'Onboarding' });
    }

    return next();
}
