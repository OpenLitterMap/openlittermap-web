@component('mail::message')

Hi {{ $user->name ?? 'there' }},

You've been given access to create a school team on OpenLitterMap.

**Before you create your team**, we recommend trying the platform yourself — upload and tag a few photos so you know exactly what your students will do. This takes about 30 minutes.

@component('mail::button', ['url' => config('app.url') . '/upload'])
Upload Your First Photos
@endcomponent

When you're ready to set up your school team:

@component('mail::button', ['url' => config('app.url') . '/teams/create', 'color' => 'success'])
Create Your School Team
@endcomponent

If you have any questions, reply to this email.

Thanks,<br>
The OpenLitterMap Team

@endcomponent
