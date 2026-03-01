@php
$imgBase = app()->isLocal() ? asset('assets/welcome') : 'https://openlittermap.com/assets/welcome';
@endphp

<x-mail::message>
# The biggest upgrade in OpenLitterMap's history is live.

OpenLitterMap has always been a community effort. More than 1,000 people have uploaded data from over 100 countries. Twenty developers have contributed code. Researchers have cited the platform in over 100 peer-reviewed papers.

But the platform itself was held back. The engineering needed to match the ambition — the refactoring, the architecture, the technical debt — required sustained R&D investment. That funding never came.

Then AI changed everything.

What used to take months now takes days. A critical locations bug that needed 2–3 months of focused work was resolved in a single Saturday. The following Saturday, the entire mobile app was refactored. Technical debt that accumulated over 17 years is now finished.

Costs continue to rise, but productivity is accelerating faster than ever. OpenLitterMap is finally becoming what it was always meant to be.

---

## What's new in v5

**Rebuilt tagging system** — the way you classify litter has been completely redesigned. Faster, more structured, and built for serious data analysis. Most existing data has already been migrated to the new system. Brands are next.

**Stronger data quality** — new admin review workflow with better filters and more reliable moderation.

**Metrics and leaderboards rebuilt from scratch** — your contributions are now tracked and calculated properly.

**Mobile app refactored** — aligned with the new backend. More stable, more improvements coming.

**810+ automated tests passing** — the strongest technical foundation OpenLitterMap has ever had.

This is no longer a fragile platform carrying 17 years of technical debt. It's infrastructure.

---

## We need your help

You built this dataset. Now we need you to test the platform that serves it.

Upload photos. Tag litter. Try things. If something doesn't work — tell us. We'll fix it fast.

<x-mail::button :url="$uploadUrl" color="success">
Log In & Test v5
</x-mail::button>

---

## We also just launched LitterWeek

<img src="{{ $imgBase }}/LitterWeekLogo.png" width="200" alt="LitterWeek — citizen science for schools and communities" style="max-width: 200px; height: auto; display: block; margin: 16px auto;">

**[LitterWeek.org]({{ $litterweekUrl }})** is a structured 5-day programme that teaches schools and communities how to collect quality citizen science data using OpenLitterMap.

Two schools have already signed up. We're recruiting more.

If you know a teacher, a school principal, a community leader, or a sustainability coordinator — forward them this email or point them to [litterweek.org]({{ $litterweekUrl }}).

<x-mail::button :url="$litterweekUrl" color="success">
Learn About LitterWeek
</x-mail::button>

---

## What's coming next

- Brand tagging migration
- Improved upload experience
- Public contributor profiles
- LitterWeek school pilots
- More mobile improvements

If you haven't logged in for a while — now is the time. The platform has changed more in the last few months than in the previous few years.

Thanks for being part of this.

— Seán
Founder, OpenLitterMap & LitterWeek

<x-mail::subcopy>
You're receiving this because you have an account at OpenLitterMap.com.<br>
[Unsubscribe]({{ $unsubscribeUrl }}) · [Your Profile]({{ $profileUrl }}) · [Privacy Policy]({{ url('/privacy') }})<br>
OpenLitterMap is free, open-source software.
</x-mail::subcopy>
</x-mail::message>
