export default function auth ({ next, store })
{
    if (store.state.user.auth) return next();

    else window.location.href = '/';
}
