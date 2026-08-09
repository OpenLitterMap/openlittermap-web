#!/usr/bin/env bash
#
# Rehearsal loop for tag retirement entry 1 (other--plastic_bag).
#
# ONE ITERATION = restore -> migrate -> rebuild Redis -> globals -> apply -> verify -> globals.
#
# The instrument IS the production gate: `olm:migrate-tag --verify` reconciles MySQL against
# Redis at every scope the objects touch. This script adds only the one thing verify cannot
# know — that NOTHING OUTSIDE the retirement moved — via six SELECTs captured either side.
#
# Determinism is the readiness bar: identical hashes for the before-globals, after-globals and
# verify output across three restores.
#
# REHEARSAL DATABASE ONLY. Refuses to run against anything but the configured rehearsal name.
#
# Usage:
#   DUMP=/path/to/production.sql ./rehearse-entry1.sh [iterations]
#
# @see readme/PostTagMigrationClean.md
set -euo pipefail

DB="${DB:-olm_postmig_2}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-secret}"
ENTRY="other--plastic_bag"
RETIRED_ID="${RETIRED_ID:-92}"
DESIRED_ID="${DESIRED_ID:-149}"
ITERATIONS="${1:-3}"
WORKING_QUEUE="storage/app/rehearsal-queue.csv"
OUT="${OUT:-/tmp/rehearsal}"

[[ "${DB}" == olm_postmig_* || "${DB}" == *rehearsal* ]] || { echo "REFUSING: '${DB}' is not a rehearsal database." >&2; exit 1; }
[[ -n "${DUMP:-}" ]] || { echo "REFUSING: set DUMP=/path/to/production.sql" >&2; exit 1; }
[[ -f "${DUMP}" ]]   || { echo "REFUSING: dump not found at ${DUMP}" >&2; exit 1; }

mkdir -p "${OUT}"
mysql_do() { mysql -u"${DB_USER}" -p"${DB_PASS}" "$@"; }
step() { printf '\n\033[1m── %s\033[0m\n' "$*"; }
fail() { printf '\033[31mFAILED: %s\033[0m\n' "$*" >&2; exit 1; }

# Six SELECTs. The first two are the "nothing else moved" claim; the rest are the two objects
# and every row that references them.
globals() {
    mysql_do -N "${DB}" <<SQL
SELECT 'tags', COUNT(*), SUM(quantity) FROM photo_tags;
SELECT 'photos', COUNT(*), SUM(xp) FROM photos WHERE deleted_at IS NULL;
SELECT 'obj', litter_object_id, COUNT(*), SUM(quantity), COUNT(DISTINCT photo_id) FROM photo_tags
  WHERE litter_object_id IN (${RETIRED_ID},${DESIRED_ID}) GROUP BY litter_object_id ORDER BY litter_object_id;
SELECT 'pivot', id, category_id, litter_object_id FROM category_litter_object
  WHERE litter_object_id IN (${RETIRED_ID},${DESIRED_ID}) ORDER BY id;
SELECT 'state', id, \`key\`, crowdsourced, retired_at, merged_into_id FROM litter_objects
  WHERE id IN (${RETIRED_ID},${DESIRED_ID}) ORDER BY id;
SELECT 'quick', clo_id, COUNT(*) FROM user_quick_tags WHERE clo_id IN
  (SELECT id FROM category_litter_object WHERE litter_object_id IN (${RETIRED_ID},${DESIRED_ID}))
  GROUP BY clo_id ORDER BY clo_id;
SQL
}

for i in $(seq 1 "${ITERATIONS}"); do
    printf '\n\033[1;44m  ITERATION %s of %s  \033[0m\n' "${i}" "${ITERATIONS}"

    step "0. clear prior run state"
    # A crashed iteration leaves a snapshot behind whose fingerprint still matches, so the next
    # iteration would silently RESUME. A rehearsal that resumes is not measuring what it claims.
    rm -f "storage/app/migrate-tag/${ENTRY}.json" "storage/app/migrate-tag/${ENTRY}.lock"

    step "1. restore ${DB} from ${DUMP}"
    mysql_do -e "DROP DATABASE IF EXISTS \`${DB}\`; CREATE DATABASE \`${DB}\` CHARACTER SET utf8mb4;"
    mysql_do "${DB}" < "${DUMP}"

    step "2. migrate (retired_at / merged_into_id)"
    php artisan migrate --force --no-interaction

    step "3. flush + rebuild Redis"
    php artisan tinker --execute="\Illuminate\Support\Facades\Redis::command('FLUSHDB');"
    php artisan olm:redis:rebuild --no-flush --batch=2000 || fail "redis rebuild did not complete"

    step "4. before globals"
    globals > "${OUT}/before-${i}.txt"

    step "5. lifecycle + apply"
    # Work from a COPY of the approved list: --advance rewrites the CSV, so rehearsing against
    # the canonical file would leave it past MAPPING_APPROVED and mutate a reviewed artefact.
    cp readme/audit/TagRetirements-2026-08.csv "${WORKING_QUEUE}"
    Q="--queue=${WORKING_QUEUE}"

    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" || fail "dry run failed"
    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --advance=CODE_UPDATED --by=rehearsal \
        || fail "advance to CODE_UPDATED failed"
    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --advance=DRY_RUN_VERIFIED --by=rehearsal \
        --evidence="rehearsal iteration ${i}: dry run counts matched" || fail "advance failed"
    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --apply || fail "apply failed"

    step "6. verify"
    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --verify \
        | tee "${OUT}/verify-${i}.txt" || fail "verify failed"

    step "7. after globals"
    globals > "${OUT}/after-${i}.txt"

    printf '\n  iteration %s hashes:\n' "${i}"
    shasum -a 256 "${OUT}"/{before,after,verify}-"${i}".txt | sed 's/^/    /'
done

step "determinism across ${ITERATIONS} iterations"
for kind in before after verify; do
    hashes=$(for i in $(seq 1 "${ITERATIONS}"); do
        shasum -a 256 "${OUT}/${kind}-${i}.txt" | cut -d' ' -f1
    done | sort -u)

    if [[ $(wc -l <<< "${hashes}") -eq 1 ]]; then
        printf '  %-7s IDENTICAL across %s runs  %s\n' "${kind}" "${ITERATIONS}" "${hashes}"
    else
        printf '  \033[31m%-7s DIVERGED:\033[0m\n%s\n' "${kind}" "${hashes}"
        exit 1
    fi
done

step "what changed between before and after (iteration 1)"
diff "${OUT}/before-1.txt" "${OUT}/after-1.txt" || true
