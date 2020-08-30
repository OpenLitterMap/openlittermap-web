export default function auth ({ next, store })
{
    if (store.state.user.auth) return next();

    return window.location.href = '/';
}
