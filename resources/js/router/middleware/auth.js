import { useUserStore } from '@/stores/user';

export default function auth ({ next }) {

    const userStore = useUserStore();

    if (!userStore.auth) {
        return next({ path: '/' });
    }

    return next();
}
