export default function admin ({ next })
{
    if (
        window.Laravel.jsPermissions.roles.includes('admin')
            ||
        window.Laravel.jsPermissions.roles.includes('superadmin'))
    {
        return next();
    }

    else window.location.href = '/';
}
