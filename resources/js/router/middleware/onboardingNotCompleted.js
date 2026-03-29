import { useUserStore } from '@/stores/user';

export default function onboardingNotCompleted({ next }) {
    const userStore = useUserStore();

    if (userStore.auth && userStore.onboardingCompleted) {
        return next({ path: '/upload' });
    }

    return next();
}
