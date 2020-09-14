export default function admin ({ next, store })
{
    if (store.state.user.admin) return next();

    else window.location.href = '/';
}
