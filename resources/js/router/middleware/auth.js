import { useUserStore } from '@/stores/user';
import { useModalStore } from '@/stores/modal';

export default function auth({ to, next }) {
    const userStore = useUserStore();

    if (!userStore.auth) {
        // Remember where the user was trying to go
        sessionStorage.setItem('intended_route', to.fullPath);

        // Show login modal
        const modalStore = useModalStore();
        modalStore.showModal({
            type: 'Login',
            title: 'Login',
        });

        return next({ path: '/' });
    }

    return next();
}
