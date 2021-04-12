export default function can_verify_boxes ({ next })
{
    if (window.Laravel.jsPermissions.permissions.includes('verify boxes')) return next();

    else window.location.href = '/';
}
