export default function admin ({ next })
{
    if (window.Laravel.jsPermissions.roles.includes('admin')) return next();

    else window.location.href = '/';
}
