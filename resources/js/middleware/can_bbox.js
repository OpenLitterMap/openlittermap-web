export default function can_bbox ({ next, store })
{
    if (store.state.user.user.can_bbox) return next();

    else window.location.href = '/';
}
