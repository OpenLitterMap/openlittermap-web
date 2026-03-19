@php
$imgBase = app()->isLocal() ? asset('assets/welcome') : 'https://openlittermap.com/assets/welcome';
@endphp

<x-mail::message>
# Welcome to OpenLitterMap

**Every piece of litter tells a story.**

Verify your email to get started.

<x-mail::button :url="$verifyUrl" color="success">
Verify Your Email
</x-mail::button>

---

## How it works

**Step 1 — Enable Geotagging**

Photos must contain GPS coordinates to be uploaded. Photos without location data cannot be uploaded at this time.

**iPhone:**
1. Open **Settings → Privacy & Security → Location Services**
2. Make sure Location Services is **ON** at the top
3. Scroll to **Camera** → set to **"While Using the App"**
4. Enable **Precise Location** for more accurate GPS (recommended)
5. **Optional & Recommended:** **Settings → Camera → Formats → "Most Compatible"** — this saves as JPEG instead of Apple's HEIC format, which means faster uploads

**Android:**
1. Open the **Camera** app
2. Tap the **Settings** gear icon
3. Enable **"Location tags"**, **"GPS tagging"**, or **"Save location"** (wording varies by device)
4. On some older devices, pull down the top menu and enable **GPS**

**Android backup — if images aren't showing GPS:**

Visit [openlittermap.com](https://openlittermap.com) in the mobile browser on the same device you used to take the photos. Log in or create an account — you'll be redirected to the upload screen.

1. Tap the white upload box
2. Tap **Upload → Browse**
3. Tap the **3 vertical dots** in the top-right corner
4. Select **Browse**
5. You should now see your photos with **4 arrows pointing outwards** — this indicates GPS is present
6. Select a photo this way and it should start uploading (other methods have been problematic on some devices)

**Step 2 — Collect Data**

**Test it first:** Go somewhere public (not right outside your home) and take a test photo. View the photo's details/info — you should see GPS coordinates or a location name. If it's there, you're ready. You can delete the test photo after.

⚠️ **Privacy note:** Do not take geotagged photos near your home or anywhere private. The GPS coordinates are embedded in the image. We recommend going to a park, street, or other public place.

We recommend collecting **5–10 test photos** first and confirming the geotagging works before making a bigger data collection effort.

We are only interested in the litter. Get close. Fill the frame. Capture the brand if visible. No shadows, no feet, no selfies — just the litter.

<img src="{{ $imgBase }}/take-photo.png" width="600" alt="How to take a good litter photo — get close, fill the frame, capture the brand, no personal info" style="max-width: 100%; height: auto; display: block; margin: 16px auto;">

**Step 3 — Add Tags**

Add the object, material, and brand.

<img src="{{ $imgBase }}/add-tags.png" width="600" alt="OpenLitterMap tagging interface — browse categories, select tags, earn XP" style="max-width: 100%; height: auto; display: block; margin: 16px auto;">

---

## What Happens Next

When you upload, your photo becomes open data:

- Your contribution appears on the global map with a real-time event
- Every upload generates a geolink you can share
- Tags become structured, searchable, reusable records
- Brands and polluters are mapped and measured

---

## What Is OpenLitterMap?

OpenLitterMap is open-source citizen science infrastructure. Used in over 100 countries. Cited in 100+ peer-reviewed academic papers. Recognised by the United Nations as a Digital Public Good.

---

## Introducing LitterWeek

<img src="{{ $imgBase }}/LitterWeekLogo.png" width="200" alt="LitterWeek — citizen science for schools and communities" style="max-width: 200px; height: auto; display: block; margin: 16px auto;">

Want to grow your skills? Take the LitterWeek challenge.

LitterWeek is a structured 5-day programme that teaches schools and communities how to collect real environmental data using OpenLitterMap. Students learn citizen science, data literacy, and better technology habits — skills that last beyond the classroom.

We're recruiting our first school partners now. Two schools have already signed up.

<x-mail::button :url="$litterweekUrl" color="success">
Take the LitterWeek Challenge
</x-mail::button>

---

## Privacy & Trust

We document litter — not people.
Photos are reviewed for quality and privacy.
Your data strengthens open science, not advertising.

---

<x-mail::button :url="$uploadUrl" color="success">
Upload Your First Photo
</x-mail::button>

[View the Global Map →]({{ $mapUrl }})

<x-mail::subcopy>
You're receiving this because you created an account at OpenLitterMap.com.<br>
[Unsubscribe]({{ $unsubscribeUrl }}) · [Your Profile]({{ $profileUrl }}) · [Privacy Policy]({{ url('/privacy') }})<br>
OpenLitterMap is free, open-source software.
</x-mail::subcopy>
</x-mail::message>
