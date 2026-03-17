# OpenLitterMap — Privacy Policy

**Last Updated:** 17 March 2026

**Data Controller:** Seán Lynch, Cork, Ireland
**Contact:** info@openlittermap.com

This policy should be read alongside your rights under the EU General Data Protection Regulation (GDPR — Regulation 2016/679) and the Irish Data Protection Act 2018.

OpenLitterMap is an open-source platform for mapping litter and plastic pollution. It relies on public participation, geotagged images, and open environmental data. Because the platform is open source, our approach to code, data, and privacy can be examined in public at [github.com/OpenLitterMap](https://github.com/OpenLitterMap).

---

## 1. Purpose

OpenLitterMap allows people to contribute geotagged photographs of litter and plastic pollution. These contributions can support research, education, public participation, and local environmental action.

At the same time, location data and timestamps can reveal where a person was at a particular moment. That creates obvious privacy considerations. This policy sets out what data is collected, what may become public, what remains private, and what choices users have.

---

## 2. Anonymous by Default

By default, OpenLitterMap does not display your identity publicly. When an account is created, name and username visibility settings are off, and public profile visibility is off. A person's name is set to null at registration — it is optional and never required.

A person may contribute data to the map without making their identity public. Users can later change these settings if they choose. These defaults are intended to reduce unnecessary exposure of personal identity.

---

## 3. What We Collect

To run the service, we need a small amount of account and contribution data:

| Data | Required | Purpose |
|------|----------|---------|
| Email address | Yes | Account creation, password recovery |
| Password | Yes | Authentication (stored encrypted, never plaintext) |
| Username | No | Auto-generated if not provided. Display is user-controlled |
| Name | No | Optional. Display is user-controlled |
| Images | Yes (to contribute) | Geotagged photographs of litter and pollution |
| EXIF metadata | Automatic | GPS coordinates and timestamp extracted from photos |
| Tags | User-applied | Litter classifications (150+ pre-defined types, plus custom tags) |

The platform does not require a phone number, date of birth, or home address. We do not see or store payment card numbers — Stripe processes optional payments directly.

---

## 4. What Becomes Public

OpenLitterMap works with location data. For that reason, it is important to be clear about what may become public.

When a photo is both visible and verified, the following may be publicly displayed: the image, its GPS coordinates, its timestamp, the tags applied to it, and whether the litter was picked up.

Your email address and password are never public. Your name and username are only public if you choose to display them.

Importantly, photo visibility and identity visibility are separate. A photo may appear on the public map without identifying the contributor. Users can set all new photos to private by default, or toggle any individual photo between public and private at any time.

School photos are always private until a teacher approves them, regardless of any other setting.

---

## 5. What We Do Not Use for Tracking

| What | Our practice |
|------|-------------|
| IP addresses | Not used for user tracking, profiling, or analytics |
| Tracking cookies | Not used. Only essential session, CSRF, and authentication cookies |
| Third-party analytics | None. No Google Analytics, Hotjar, Mixpanel, or equivalent |
| Advertising pixels | None. No Facebook Pixel or advertising scripts |
| Referral/destination URLs | Not logged |
| Clickstream or behavioural data | Not collected |

We load Stripe to process optional payments and Font Awesome for icons. We do not use these services for advertising, analytics, or behavioural profiling.

We use only essential cookies needed to operate the service.

---

## 6. Privacy Controls

Users have control over their visibility on the platform. All identity settings default to off.

| Setting | What it controls | Default |
|---------|-----------------|---------|
| Show name on maps | Your name next to your map points | Off |
| Show username on maps | Your username next to your map points | Off |
| Show name on leaderboards | Your name in rankings | Off |
| Show username on leaderboards | Your username in rankings | Off |
| Public profile | Whether others can view your profile page | Off |
| Photos public by default | Whether new uploads appear on the public map | On |
| Per-photo visibility | Toggle any individual photo between public and private | — |
| Prevent others tagging my photos | Block other users from adding tags to your uploads | Off |

These are independent controls. A person can show their username on leaderboards without revealing it on the map, or vice versa.

Settings can be managed at [/settings](/settings).

---

## 7. Your Rights

Under GDPR, users have a number of rights. The platform provides the following:

**Right to erasure (Article 17).** Users can delete their account at any time from their settings page. This is a permanent deletion — personal information (name, email, username, OAuth tokens, verification logs, roles, team associations, leaderboard entries, statistics) is destroyed. Photos are preserved as anonymous contributions to the open dataset with the user ID nullified. Password confirmation is required.

**Right to data portability (Article 20).** Users can export their data as CSV from their profile. The export includes photo metadata, tags, GPS coordinates, timestamps, and classification data. No personal information is included in any export.

**Right to rectification (Article 16).** Name, username, and email can be updated at any time in settings.

**Right to restrict processing (Article 18).** Photos can be set to private, visibility toggles can be disabled, and other users can be prevented from tagging your photos.

**Right to object (Article 21).** Contact info@openlittermap.com. We will action any request promptly.

**Right to lodge a complaint.** Users may contact the Irish Data Protection Commission at [dataprotection.ie](https://www.dataprotection.ie).

---

## 8. Young People & Schools

OpenLitterMap is not intended for children under 13. If we become aware that a child under 13 has provided personal data, we will remove it.

Users aged 13–17 may participate with parental or guardian supervision only. Supervisors should ensure safe practices, particularly around hazardous litter.

School teams have additional safeguards implemented in the platform, not stated only as policy:

- All school uploads are private by default and do not appear on the public map, regardless of user settings.
- A teacher must approve each upload before it becomes publicly visible.
- Student identities are automatically masked in public-facing contexts — other users see "Student 1", "Student 2", not real names.
- Only teachers and team leaders can see real student names, for classroom management purposes.

If you are a school, educator, or youth organisation, [LitterWeek.org](https://litterweek.org) provides guided digital skills training for collecting high-quality environmental data safely and responsibly.

**If you or a young person suffers an injury, call 112, 999, or your local emergency number immediately.**

---

## 9. Verification and Visibility

Contributions do not all move through the platform in the same way. Visibility depends on both privacy settings and review status.

New users' tagged photos are stored but do not appear on the public map until reviewed by an administrator. Once an account is upgraded to trusted status, based on consistent and high-quality contributions, tagged uploads appear on the map without delay.

School uploads require teacher approval before becoming public, regardless of trust status.

In practice, the public map displays only photos that are both marked as public and have been verified by an administrator, trusted user, or teacher.

---

## 10. Legal Basis for Processing

| Processing activity | Legal basis (GDPR) |
|--------------------|--------------------|
| Account creation and authentication | Contract performance (Art. 6(1)(b)) |
| Publishing geotagged environmental data | Legitimate interest (Art. 6(1)(f)) — open environmental science |
| Optional display of name/username | Consent (Art. 6(1)(a)) — user-controlled, off by default |
| Service emails (password resets, account notices) | Contract performance (Art. 6(1)(b)) |
| Optional updates or marketing | Consent (Art. 6(1)(a)) |
| Payment processing via Stripe | Contract performance (Art. 6(1)(b)) |

---

## 11. Data Retention

- **Account data:** Retained until the account is deleted.
- **Photos and tags:** Retained as part of the open dataset. If a photo is deleted, it is removed. If an account is deleted, photos are anonymised and retained as open data.
- **Server logs:** Essential logs only, rotated regularly.
- **Payment records:** Retained as required by financial regulation.

---

## 12. Data Sharing

We do not sell, rent, or share personal data with any third party.

The only external service that receives user data is Stripe, which processes optional payments. Stripe's privacy policy applies to payment data.

The open dataset (geographic data, tags, timestamps) is shared with the world under ODbL. It contains no personal information.

---

## 13. Security and AI-Assisted Development

Version 5 of OpenLitterMap was developed with significant use of AI-assisted coding tools. We are disclosing this because software produced quickly still requires careful review. The codebase remains open to inspection, testing, and improvement by maintainers and contributors.

If you identify a security concern, please contact info@openlittermap.com.

---

## 14. Safety

- Do not touch hazardous litter (needles, glass, chemicals) without proper protective equipment.
- Do not collect data in unsafe locations or near busy roads.
- Avoid including identifiable people in photographs — faces, tattoos, licence plates, or distinctive features.
- Do not upload from locations you wish to remain private.
- Remain vigilant of theft and people who may take your device while it is unlocked.
- Report any safety concern to info@openlittermap.com.

---

## 15. Changes

We update this policy as the platform evolves. Material changes will be communicated via the website. The date at the top of this page reflects the most recent revision.

---

## 16. Contact

For any privacy question, data request, or concern:

**Data Controller:** Seán Lynch
**Email:** info@openlittermap.com
**Location:** Cork, Ireland
**Code:** [github.com/OpenLitterMap](https://github.com/OpenLitterMap)

You may also contact the Irish Data Protection Commission at [dataprotection.ie](https://www.dataprotection.ie).
