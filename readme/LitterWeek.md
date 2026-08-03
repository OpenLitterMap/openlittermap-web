# LitterWeek — Product Specification

> The master product definition. Supersedes the review documents; incorporates the July 2026 audit cycle (internal review + two external review syntheses) and the year-programme reframe. Per-day and per-lesson docs describe implementation; where they conflict with this spec on product intent, this spec wins. Where this spec conflicts with shipped code on current state, code wins and the gap goes on the roadmap.

---

## 1. What LitterWeek Is

LitterWeek is **Week 1 of a 52-week digital skills programme** for Irish Transition Year students (ages 14–16), delivered in school. Week 1 is the ignition: a five-day training week that turns passive phone users into trained operators of a scientific instrument, evidenced by real contributions to OpenLitterMap, a UN Digital Public Good. Weeks 2–52 are the engine: a year-long curriculum — field operations, file management, data analysis, coding, GIS — in which every skill is exercised on the evidence dataset the class started building in Week 1.

It is a **digital health intervention delivered through citizen science**, not an environmental awareness campaign. Litter is the training material because it is abundant, photographable, classifiable, and consequential — but the product being sold is the method: observe → capture → structure → locate → analyse → publish → communicate. Week 1 teaches the method. The year applies it until it is a profession-shaped capability.

The week produces three outputs: a trained student who can make evidence-quality judgements independently; a real dataset on the global map; and a class presentation to a real audience. The year produces a fourth: **a portfolio** — verified observations, a cleaned dataset, analysis, working code, and a map, all made from the student's own evidence.

**The thesis, verbatim, unchanged:** *The same phone that collects data about you can collect evidence for you. Most people never learn the difference.*

---

## 2. Who It Serves

| Audience | What they get | What they do |
|---|---|---|
| **Students** | A capability, not a topic: field data collection in Week 1; analysis, coding, and GIS across the year — always on their own data. STEM identity with receipts. | Join by 6-char team code. No accounts, no email, no personal data. Team-level progress only. |
| **Teachers** | A packaged programme that runs itself except at defined control points. Real-time team progress; mastery evidence, not gamification, in their interface. | Register, create session, control fieldwork start/stop (Day 3), publish (Day 4), administer paper surveys (Days 1 & 5), run weekly missions from Week 2. |
| **Schools** | A defensible year-long digital-skills offering with a UN DPG credential, measurable outcomes, and parent-visible results. | Buy per-year licence (§15). |
| **Parents** | Proof the phone can be used deliberately: certificate, a link to their child's data on a world map, one page explaining what was learned — and artefacts arriving across the year. | Attend the Day 5 presentation where offered. |
| **OpenLitterMap** | Higher-quality, geographically distributed uploads from trained contributors, sustained for a year — and a measurable answer to "does training improve data quality?" | Receives all photos and tags. LitterWeek never touches them. |

---

## 3. The Six-Star Standard

What "the programme every school, student, teacher and parent wants" means, concretely. Six commitments; a feature that breaks one is not six-star and doesn't ship.

1. **Never a toy dataset.** From the first pixel to the Week 52 map, every exercise runs on evidence the class created. File management is taught on their files. Analysis on their numbers. Code against their uploads. GIS on their points.
2. **Every skill leaves a receipt.** An observed judgement, a portfolio artefact, or a public contribution — never a certificate of attendance.
3. **The real thing is the scene.** Real satellite reflectance, real EXIF, real citations, real map, real audience. Illustration warms up; it never upstages the real data it introduces.
4. **Runs on school reality.** Chromebooks at 1366×768, Irish weather, timetables, mixed ability, and a teacher with ≤10 minutes of prep per week.
5. **Something goes home.** Every milestone produces an artefact a parent can hold or click — and it cites the child's own words and numbers, not boilerplate.
6. **Finished means independent.** A unit is done when the student can make the judgement or perform the task without the platform. Completion is a gate; independence is the goal.

---

## 4. Product Laws

Non-negotiable across every week, lesson, and future feature. A dispatch that violates one is wrong by definition.

1. **Measurement over awareness.** Every screen must introduce genuinely new knowledge or exercise a judgement. No recycled NGO talking points.
2. **Interaction before explanation.** Discovery first, naming second.
3. **One concept per lesson; one signature mechanic per lesson.** No two lessons feel the same.
4. **Every lesson ends with one field rule.** One sentence, procedural, carried into the real world. The field rules are the durable curriculum (§11).
5. **Skills observed, not self-reported.** Week 1 Day 2 trains judgement; **every day after Day 2 — and every week after Week 1 — must observe at least one trained skill being applied** inside the platform. This is the line between a logistics tool and a training product.
6. **Evidence, not points.** No visible score accumulation. Assessment is completion + mastery evidence + telemetry (§9). Checkpoints and Field Clearance are the gates.
7. **Zero free text in assessed interactions.** Sole scoped exception: the unassessed Day 2 graduation pledge sentence.
8. **Derived figures, never hardcoded.** Any number shown to a student derives from the real asset or real data, or it doesn't ship.
9. **Real data wherever possible.** Simulated data is a documented fallback, never the default.
10. **The student is 16.** Dry humour, uncomfortable truths, no educational-poster language. Every student-facing line passes the anti-cringe checklist (Narrative.md) before shipping.
11. **Training mode 90%, Ceremony mode 10%.** Ceremony lands at boundaries and genuine milestones. Max one emerald CTA per screen. Solemnity and spectacle stay separate.
12. **Gary appears at emotional peaks only.** One line per appearance, maximum three lines per lesson. The joke is never on the students. `gary-lines.ts` is protected: placements move, lines and keys change only through owner sign-off.
13. **LitterWeek never stores photos, proxies uploads, or calls OLM APIs.** All OLM interaction is manual via external links. Structural, not temporary.
14. **Student-facing copy ships only with owner sign-off**, arrives verbatim in build dispatches, and is never drafted or altered by the engineering agent.
15. **Runtime budgets are enforced** (§12). Until a unit meets its budget, every new required beat must replace or shorten an existing required beat.
16. **Progression logic lives in one place.** `ProgressionService` is the single authority for gating; CTAs and copy never encode routing rules. Direct URLs restore correct state or explain the missing prerequisite.
17. **No fragile browser APIs as gates.** Live sensors are optional enhancement; the fallback is the primary path.
18. **Safety before data.** If it's not safe to reach, it's not worth the photo. No private property, no traffic risk, no faces, no plates, no addresses — in training content, in student uploads, and in our own assets.
19. **No toy datasets.** Every skill in the year is exercised on the class's own accumulating evidence. If a proposed unit needs invented data, the unit is wrong or the year hasn't produced its prerequisite yet.
20. **The real thing is the scene.** Where real imagery or data exists, it is the set, not the payoff — an illustrated warm-up never runs longer than the real interaction it introduces.

---

## 5. The Competency Spine

Week 1 trains one method, assessed at five levels; the year deepens it into four professional capabilities. This table is the product; everything else is delivery.

### Week 1

| Day | Competency | Where trained | Where observed applied | Evidence |
|---|---|---|---|---|
| 1 | Baseline honesty: what do I actually use this device for? | Paper pre-survey + intro session | — (baseline) | Paper survey (teacher-held) |
| 2 | The instrument: pixels are measurements; the phone is a multi-sensor field instrument; capture and classification standards; where data goes and why openness matters | Lessons 1–5 + Field Clearance | Checkpoints, Clearance, predict-verify interactions | `pixel_understanding`, `device_record`, `evidence_protocol`, `open_data_understanding`, `graduation_pledge` |
| 3 | Intentional capture under real conditions | Day 2 L3 | **Evidence QA: team judges its own photos against the capture rules before upload** | `field_collection_log`, `upload_confirmation` |
| 4 | Structuring and honest analysis | Day 2 L3 tagging; Day 4 evidence-limits | **Findings must cite the team's own transcribed OLM numbers** | `tagging_log`, `map_review`, `habits_analysis` |
| 5 | Public communication of evidence, with limits acknowledged | Day 4 | Storyboard → slides → rehearsal → delivery to a real audience; **the transfer question** | `storyboard`, `slide_content`, `rehearsal_complete`, `closing_ceremony` |

**The transfer question is a required product feature.** One screen, Day 5, before the ceremony: the method works for litter; where else could it apply? It is the product's claim to generality — and from Week 2, it is the bridge into the year.

### The Year (structural commitment; per-week curriculum arrives as its own spec)

| Block | Capability | Exercised on |
|---|---|---|
| Operator (≈ Weeks 2–13) | Sustained field collection, file management, data hygiene | Their own photo library, OLM exports, naming/formats/backup of their evidence |
| Analyst (≈ Weeks 14–26) | Spreadsheets, cleaning, charts, basic statistics, honest comparison | Their exported dataset; school vs national OLM data |
| Coder (≈ Weeks 27–39) | Data structures, APIs, first scripts | Their records as JSON; queries and filters against their uploads |
| Cartographer (≈ Weeks 40–50) | GIS: web mapping, hotspot and cluster analysis, change over time | Their points; before/after of their patch of Ireland |
| Capstone (Weeks 51–52) | The Annual Report: a year of evidence presented; the next question chosen | Everything above, assembled into the portfolio |

---

## 6. The Week

### Day 0 — Joining
One input field, one join code, no account. Code resolves to team + session; the Hub (journey map) becomes home base for the week and, from Week 2, the year.

### Day 1 — Activate (~1 session)
Paper pre-survey (teacher-administered; the measurement instrument for the intervention claim) plus the introduction: attention audit, why this matters, OLM, the map, the mission. Day 1 carries no in-app badges — the survey lives on paper by policy for a clean pre/post comparison.

### Day 2 — Train (median 22–24 min, hard ceiling 30 min incl. Field Clearance)
The Digital Skills Bootcamp. Five lessons, sequentially gated by evidence, each with a signature mechanic and a field rule. Desktop/Chromebook delivery, one device per team.

| Lesson | Title | Signature mechanic | Field rule theme |
|---|---|---|---|
| 1 | **The Pixel** | The Trace: scrub one pixel backward through the imaging chain to the world; magnifier over a real litter photo; real Sentinel-2 predict-verify with **NIR as predict → toggle → interpret** | The record is the evidence (authored via copy cycle) |
| 2 | **The Device** | Five sensor micro-challenges — do what the sensor measures; build one structured observation; watch dots become a pattern **and tap the hotspot**; Safe/Caution/Never scenarios | Public spaces only; not safe to reach, not worth the photo |
| 3 | **Field Protocol** | Timed A/B evidence triage (untimed mode available) earning the six capture rules; one item at three real distances with derived pixel counts; tagging practice with **brand as controlled selector** | "Can't read it? Retake. Closer." |
| 4 | **Open Data** | **What your photo exposes** (the operational privacy anchor) → the three openness checks on contrasting destinations **including one that fails and "unclear" as a valid answer** → the pipeline from photo to public evidence, with a **real citation** | Authored via copy cycle |
| 5 | **Graduation Pledge** | Chip-built commitment + one free-text sentence (the sole free-text exception), read back on Day 5 | The pledge is the rule |

**Field Clearance sits between L4 and L5** (8 questions, ≥6 to pass, 10-attempt ceiling). Routing enforces this; no CTA bypasses it. Lesson 1 keeps its three-phase spine plus the Trace and the object-vs-record commit. **Cut permanently: the Time Tear, the blackout, the decorative lock.** The capability record is the Signal Ribbon completing — WORLD · MEASURE · STORE · SHOW · INTERPRET — with no second chain representation and no visible score. L3's one operational privacy decision (what does this photo expose — check before capture) stays in Field Protocol; ownership, licensing, and reuse live in L4.

**Phase 3 (The Satellite) is under redesign** per Law 20: the shipped illustrated orbital pass runs longer than the real Sentinel interaction it introduces. Redesign direction — the real imagery becomes the scene — is gated on the engineering information audit (§16 P0.0): actual beat timings, what the Sentinel pipeline already holds on disk, and the coupling between the orbital scene and the predict-verify.

### Day 3 — Collect (~55 min + travel)
Teacher starts and stops fieldwork; students poll for the signals. **No indoor fallback — if the class can't go outside, the day moves.** Stated at booking, with a **reschedule protocol in the teacher documentation**: the rule and the email, not a built flow.

| Lesson | What happens | Skill observed |
|---|---|---|
| 1 | Mission Briefing: equipment, route, roles (rotating Picker/Mapper/Bin-man), safety recap, GPS test, **upload readiness — school OLM account logged in and credentials confirmed** | — |
| 2 | Fieldwork: companion-screen field mode outside. On stop, the return-to-class screen runs **Evidence QA**: the team selects its best and worst photo and tags which of the six capture rules the worst breaks | **First observed application of Day 2 training** |
| 3 | Class Upload: every team's device into the shared school account, uploading concurrently while the teacher narrates the projector; pins land in real time. Close-out folds in the debrief and tomorrow preview | Upload discipline; pins confirmed on the school map |

The projector moment is the emotional peak of the week and is protected as such — including against failure: **the teacher script carries a dignified fallback** (a failed team re-uploads via the teacher's device while narration continues; no team is left exposed mid-ceremony).

### Day 4 — Decode (~60 min)
Tagging Guide → Map Review → **The Publish Moment** → Habits Analysis → Closure. **Habits Analysis opens with a numbers readout:** the team transcribes its own OLM stats — total uploads, top three objects, top material, top brand — and every finding must cite at least one transcribed number, anchored by one static global comparison. The evidence-limits exercise remains the day's intellectual centre. Day 4 checkpoints get authored; the empty registry entry is debt, not design.

### Day 5 — Present (~100 min)
Fully student-driven. Paper post-survey (identical Likert items to Day 1) teacher-administered in the afternoon window.

Reveal → Storyboard → Slide Builder → Rehearsal → **Transfer Question** → Closing Ceremony.

**The Reveal reads real data back:** the student's own Day 2 pledge sentence and capability record, set against what the team actually did. **The presentation has a defined audience** — default another class or the principal; recommended a parents' session. The parent artefacts — certificate and one-page brief — **are designed before the Day 5 build so the Reveal and Storyboard feed them directly**: the brief cites the child's pledge sentence, the team's OLM numbers, and their transfer choice; the certificate certifies the capability and the contribution, never attendance (final copy via the copy cycle). Schools get the parent session as a package — invitation email, 15-minute deck, handout — because a session that isn't trivial to organise doesn't happen. The school-map link is the hero button.

---

## 7. The Year (Weeks 2–52)

The engine. Weekly cadence, one skill per week, one mission per week, every skill exercised on the class's own accumulating dataset (Law 19). **Gary's Dispatch** is the delivery frame: the recovering hedge-fairy becomes the mission-giver — River Week, Brand Hunt, Cigarette Butt Week, Bus Stop Week — and each mission pairs a field objective with the block's skill objective.

- **Operator block** — collection becomes routine; file management and data hygiene taught on the artefacts the routine produces. The Litterdex (discovery collection over the OLM taxonomy) and Fog of War (hex-grid coverage of the school's area) land here: completionist and exploration drives in service of geographic spread and taxonomy fluency.
- **Analyst block** — the dataset is now big enough to mean something. Export it, clean it, chart it, compare school vs national. Evidence-limits reasoning from Day 4 returns at scale.
- **Coder block** — the Hacker track: their records as JSON, repairing structures, discovering how the API works, building first queries and scripts against their own uploads. A TY student who finishes this block has debugged an API call and contributed validated geospatial data to a UN Digital Public Good — a portfolio piece, and the Premium tier's justification.
- **Cartographer block** — GIS on their own points: web mapping, hotspot and cluster analysis, change over time on their patch of Ireland. The Journey Map was the metaphor; this is the real thing.
- **Capstone** — the Annual Report, presented; the portfolio assembled; the transfer question graduates from a screen to a decision: the class picks the next issue to map.

**The Portfolio** is the year's receipt (Six-Star #2): the Operator Logbook capabilities accumulate into named artefacts — N verified observations, a cleaned dataset, charts, a working script, a map, two presentations. Alliances (class/school/regional leaderboards, mapping onto the ETB sales structure) land where competition helps, never before.

All twenty laws apply to every week. The missions engine (Gamification.md schema: missions, prerequisites, validated attempts, server-side validation) is the platform for the year; the anti-checkbox contract (§10) applies to every mission.

---

## 8. The World

The delivery mechanism for the spine. It gets students through the door; the content changes how they see their phone. Neither works alone.

- **The Hub / Journey Map** — painted overworld, five biomes, fog retreating as days complete. The fog is the Fog of Vibes: data-free certainty, performative concern. From Week 2 the Hub becomes the year's home: the dispatch board replaces the day cards; the real fog-of-war hex map eventually replaces the painted metaphor with the class's actual coverage.
- **Doors, chests, keys, the five emojis** (Spark, Brain, Pin, Lens, Voice) — reward *named capabilities*, not completion. **The Vault** — five keys; the treasure is 📱 the phone.
- **Gary** — recruiter, guide, pressure-valve, witness, celebrant in Week 1; mission-giver for the year. Governed by Law 12. The wand restores as data quality improves, not as belief increases.
- **Ceremony placement** — boundaries and true milestones only: clearance, first pins, publish, vault, block completions, the capstone.

Teachers never see any of this. Their interface is functional; they are the facilitator, not the player.

---

## 9. Assessment & Evidence Model

Three separated concepts, replacing visible points:

1. **Completion** — the team reached and finished the required activity. Drives gating via `ProgressionService`.
2. **Mastery evidence** — semantic events proving a judgement was made: the pixel-zero commit, surface prediction, capture-rule identified in QA, evidence-limits responses, clearance passed. Stored in typed evidence payloads; the basis of teacher reporting, the portfolio, and pilot analysis.
3. **Telemetry** — selections, retries, skips, abandonment, per-beat timing. Feeds runtime governance and the pilot.

**Sequencing rule for the migration: schemas first, deletion second.** The typed `EvidenceContentRules` schema for every Day 2 evidence type is written and enforced before any score code is removed; otherwise implicit scoring re-enters through vague payloads. `setLessonScore` consumers are audited before deletion.

**Teachers see mastery evidence, not gamification:** a per-team competency view — which judgements were made, where teams struggled, what to revisit — is a named Phase F deliverable, designed against the schemas above.

**Every scored interaction specifies its failure path:** what an incorrect choice triggers, whether retry is permitted, whether the answer is revealed, whether understanding must be demonstrated after the reveal, and that progress survives refresh. A wrong answer creates instruction, not a red state.

---

## 10. What the Platform Observes (the anti-checkbox contract)

| Unit | Self-reported (allowed) | Platform-observed (required) |
|---|---|---|
| Day 2 | — | Every commit, prediction, sort, triage, tag, checkpoint, clearance |
| Day 3 | Photo count | Evidence QA judgement (best/worst + rule broken); pins-seen confirmation |
| Day 4 | Tag count | Transcribed OLM numbers; findings citing them; evidence-limits responses |
| Day 5 | — | Storyboard/slides content; transfer selection; pledge readback viewed |
| Weeks 2–52 | Field counts | At least one skill-application judgement per mission, per Law 5 |

If a proposed unit adds only self-reported numbers and confirmations, it fails Law 5 and doesn't ship.

---

## 11. The Field Rules

The durable curriculum — what a student still knows a year later. One per lesson, procedural, one sentence. Existing approved rules stand verbatim; missing ones are authored through the copy cycle, never by the engineering agent.

| Source | Rule |
|---|---|
| D2 L2 | "Safe collection rule: public spaces only. If it's not safe to reach, it's not worth the photo." |
| D2 L3 (master) | "Can't read it? Retake. Closer." |
| D2 L3 (capture) | The six capture rules earned in triage: get close · hold steady · check your shadow · frame only the item · item visible and identifiable · watch for glare |
| D2 L3 (tagging) | "Category → Object → Material → Brand. If you can't read the brand, tap 'Not visible.' Never guess." |
| D2 L1, L4 | To be authored (record-is-evidence; open-destination) |
| D2 L5 | The student's own pledge |
| Weeks 2–52 | One per block, authored with each block's spec |

---

## 12. Governance & Quality

**Runtime.** Day 2: median 22–24 min, P90 ≤28, hard ceiling 30, including Clearance; Lesson 1 ceiling 10 min. Day budgets per §6; weekly missions budgeted per block spec. Charter numbers are amended from observed pilot data, not estimates; until then, displace-or-die (Law 15). Per-beat timing lands via telemetry before the pilot.

**Reference environment** — explicit, per context:

| Context | Environment | Status |
|---|---|---|
| Day 2 / weekly lessons | School Chromebook 1366×768 | **Required — the reference device** |
| Day 2 / weekly lessons | Desktop/laptop ≥1024px | Required |
| Day 3 / mission fieldwork screens | Student phone, portrait | Required (companion-screen mode) |
| Lessons on phone portrait | — | Unsupported |
| Tablet landscape | — | Explicit decision per feature |
| Keyboard-only | All contexts | Required regardless of viewport |

Vertical height, browser zoom, and Chromebook decode performance are first-class constraints. Image payloads are budgeted per phase; **the count-asset resolution decision is made in writing before the Cork shoot**, so the brief is unambiguous.

**Accessibility acceptance (every dispatch):** keyboard operability with visible focus; screen-reader names and state announcements; 200% zoom without clipped controls; no colour-only meaning (NIR and status states carry labels or patterns); minimum target sizes; timed tasks have an untimed or extended mode; reduced motion collapses animation without losing content.

**Assets.** Every student-facing photo: real EXIF retained in source; no faces, plates, or private addresses; orientation normalised; provenance and licence recorded; derivatives reproducible by script; stable filenames. The Cork shoot brief carries these as acceptance criteria and covers, in one pass: the L1 count asset, the L3 same-item-three-distances set, and the Day 3 exemplars.

**Experience quality as recurring practice.** Each major cycle ends with the engineering agent's candid **weakest-five list** — the five most fragile or underwhelming frontend experiences, one line each on why — feeding the next visual pass. The first list is P0.0. Copy is **hallway-tested with actual TY students** before the pilot — not teachers, not parents.

**Documentation.** Spec custody stays with the product owner; dispatches are self-contained with copy verbatim; repo docs describe shipped state only; code wins on disagreement. Copy citations use verbatim strings, not line numbers. `CURRENT_IMPLEMENTATION.md` is the GOODNIGHT-owned session handoff, never a state authority.

---

## 13. Architecture

**Stack.** Laravel 12 · Inertia.js · Vue 3 · Pinia (+ persistedstate) · Tailwind · GSAP · Leaflet · Chart.js · canvas-confetti. Fortify/Sanctum session auth for teachers; `ResolveTeamSession` middleware for students. SQLite :memory: test suite.

**Roles.** Teacher (account) / Student (team session, no account) / Admin. Students are never individually tracked in Week 1; all progress is team-level. Any individual attribution in the year (portfolio, Litterdex) is an explicit future decision with its own privacy design — never a default.

**State.** One progression model, one authority:

```
not_started → in_progress → checkpoint_passed → lesson_completed
   → clearance_passed (Day 2) → day_completed → programme_completed
   → weekly_mode (missions engine)
```

`ProgressionService` gates every save: cross-day, within-day, and the teacher-controlled gates (`fieldwork_started_at` / `fieldwork_stopped_at`, `published_at`). Evidence payloads are validated server-side against typed schemas (`EvidenceContentRules`). Pinia persists to localStorage for resilience; server evidence re-syncs stores on load. Polling with exponential backoff for teacher signals; CSRF retry; teacher mutations transactional and idempotent. The Weeks 2–52 missions engine follows the Gamification.md schema — missions, prerequisites, server-validated attempts — under the same single-authority rule.

**The OLM boundary.** Take photos: student's camera. Upload, view, review, delete: openlittermap.com, manually, via the shared school account. LitterWeek tracks completion steps and judgements only (Law 13).

---

## 14. What LitterWeek Is Not

- Not an awareness campaign, and never marketed as one.
- Not a photo platform — it holds zero images.
- Not a surveillance tool — no student accounts, no individual tracking, no personal data beyond a teacher email.
- Not a quiz engine — recall is the minority interaction; judgement is the product.
- Not a content library — Weeks 2–52 are missions on the class's own data, not a video course.
- Not a general smartphone or privacy-law course.
- Not dependent on weather-proofing — fieldwork moves; it is not simulated.
- Not finished when the interaction completes — finished when the student can do it independently.

---

## 15. Commercial Shape

The annual licence now sells 52 weeks, not 5 days — the week is the funnel, the year is the product, and the price defends itself against any one-off workshop.

| Tier | Level | Price | Includes |
|---|---|---|---|
| Standard (Explorer) | Primary | €950–€1,450 / yr | Week 1 + Operator/Analyst-level year, **parent layer included** |
| Premium | Secondary / TY | €1,950–€2,950 / yr | All blocks incl. the Coder block (Hacker track) and Cartographer |
| ETB / regional bundle | Multi-school | €10k–€60k / yr | Regional alliances, cross-school leaderboards, coordinator reporting |

**The parent layer ships in Standard** — it is the referral engine, and a distribution channel is never paywalled. Differentiators no competitor holds: a real geospatial platform underneath; data cited in 100+ peer-reviewed papers; UN Digital Public Good recognition; student activity that produces genuine research data and a genuine portfolio. Alignment with Ireland's EU Council Presidency citizen-science agenda (MAPS 2026) is the policy tailwind.

---

## 16. Measuring Success

Efficacy is measured in the platform the programme feeds — the ultimate expression of measurement over awareness.

**The flagship pilot correlation:** Day 3 Evidence QA accuracy (does the worst photo actually break the rule the team says it breaks?) against the OLM verification pass-rate of that team's uploads. This is the "does training improve data quality?" answer, in one chart.

**OLM-side (ground truth):** verification pass-rate of school uploads vs untrained baseline; tag-correction rate; photos per student; % of teams reaching publish; sustained weekly contribution after Week 1.

**Programme-side:** paper pre/post Likert deltas; field-rule recall after Day 5; transfer articulation on an unseen scenario; median and P90 runtimes vs budget; abandonment points; teacher interventions.

**Distribution-side:** parent attendance where offered; the one-sentence test — a student telling a parent, unprompted, some version of the thesis.

**Pilot gate:** one observed, instrumented pilot before any year-block build. **"Green" is defined quantitatively before the pilot runs** — the runtime P90, evidence-validity rate, and teacher-intervention thresholds that constitute go/no-go — so the pilot produces a decision, not just data. **Pilot-school recruitment starts now, in parallel with P0** — finding a TY teacher willing to run an unproven week with a hard outdoor requirement takes longer than building features; this spec is the recruitment document. The key metric is never "completed the interaction"; it is "can now make the intended judgement independently."

---

## 17. Roadmap

### P0 — Finish Day 2 + de-risk the pilot

*Dispatch map: A1=#2 (issued) · A2=#9 (issued) · A3=#5 · A4=#8 · A5=#10 · A6=#4 · B1=#1 (copy cycle open, L4 V1 drafted) · owner decisions/copy: #3, #7 strings, #11–15.*

0. ✅ **Engineering information audit** — complete (`AUDIT_2026-07-18.md`); findings folded in below. Headline: Day 2 is a dead end at L4; Habits saves the answer key; scoring is write-only theatre; telemetry is nothing; the Hub is keyboard-inaccessible.
1. ⬜ **Build Day 2 L4 + L5.** L4 is a terminal stub saving no evidence, pinning `nextLesson` at 4 — Field Clearance, the pledge, and everything past Day 2 L3 are unreachable in normal flow. L4 copy cycle (privacy split + discriminative destinations + "unclear" + field rule) opens immediately; L5 builds per Day2Graduation.md. Supersedes the refuted routing-fix item.
2. ⬜ **Day 4 Habits integrity fix** — shipped code persists `correctHabit` as the team's `habit_matches` regardless of input. Save real selections. Data-integrity bug in an evidence product; pulled forward from P1.
3. ⬜ Count-asset resolution decided in writing → Cork shoot brief → shoot (against §12 asset criteria)
4. ⬜ L1: remove the decorative lock (the Lesson-2 tease moves to record confirmation); Time Tear and blackout stay unbuilt; Phase C = object-vs-record commit only
5. ⬜ Evidence schemas for all five Day 2 types (currently all permissive), **then** scoring removal — audit confirms the visible score has zero store, backend, or teacher consumers, so the migration is small
6. ⬜ L3 → Field Protocol; brand selector; timer accommodation
7. ⬜ NIR as predict → toggle → interpret (`forest-nir.jpg` confirmed on disk, currently never loaded; strings via copy cycle)
8. ⬜ Telemetry v1 — from zero: timestamp/duration on events, a sink endpoint, production flush, team identity
9. ⬜ Asset & payload hygiene — purge ~50 MB of orphan rasters; compress `gary.png` (2.42 MB, loaded every phase); remove the Phase-2 cache-buster; restore `apple-touch-icon.png`; fix the Day 3 zero-photo gate (`>= 0` → `> 0`)
10. ⬜ A11y pass 1 — Hub `JourneyMap` keyboard navigation + roles (the main navigation is currently mouse-only), `ChestOverlay`/`CompassCeremony` focus management, global reduced-motion CSS baseline
11. ⬜ Parent brief + certificate designed (copy via cycle) so Day 5 can feed them
12. ⬜ Day 3 upload-readiness check + projector fallback script; weather reschedule protocol in teacher docs
13. ⬜ Gary batch: author the three missing keys (`day2-l4-choice`, `day2-l4-complete`, `day2-l5-pledge`); decide the three shipped-but-dead exports (idle mutters, field radio, dynamic lines); Accumulator remap; L1 to three lines
14. ⬜ Pilot school recruitment opened; "green" criteria drafted
15. ⬜ Hallway copy test with TY students

### P1 — Days 3–5 become a training product; the experience pass
1. ⬜ Day 3 Evidence QA; Tomorrow folded into upload close-out
2. ⬜ Day 4 numbers readout + citation requirement + global comparison — **replacing the shipped habit-matching beat** (the documented findings flow was never built); Day 4 checkpoints authored
3. ⬜ Day 5 pledge/capability readback; transfer question; audience + parent session package
4. ⬜ Teacher mastery-evidence view (Phase F)
5. ⬜ **Satellite redesign per Law 20** — must also eliminate `pass2`'s per-frame ~900-rect mutation and `getPointAtLength()` calls (the audited Chromebook risk) + visual pass on the builder's weakest-five
6. ⬜ Full product-owner smoke test of the week; frontend test coverage extended to Days 3–5, teacher, and admin pages (currently zero)
7. ⬜ Instrumented pilot → go/no-go against the pre-defined green criteria

### P2 — The Year (gated on pilot green)
Missions engine → Operator block (+ Litterdex, Fog of War) → Analyst block → Coder block (Hacker track, Premium) → Cartographer block → Capstone + Portfolio → Alliances where competition helps. Each block ships with its own spec, field rule, runtime budget, and anti-checkbox contract. No block enters the build until correctness, runtime, evidence validity, accessibility, and progression are measured green.

---

## 18. Related Docs

| Document | Covers |
|---|---|
| DesignCharter.md | Day 2 pattern rules (amended per §4) |
| Day-level docs (Day1–Day5) | Per-day implementation state |
| Per-lesson specs | Lesson implementation + verbatim copy |
| LitterFairy.md / Narrative.md | Gary, the world, the anti-cringe checklist, the emotional architecture |
| Gamification.md | Missions engine schema; Litterdex, Fog of War, Alliances, Hacker track (now the Year's blocks) |
| Architecture.md / Database.md / Routes.md / Testing.md | System reference |
