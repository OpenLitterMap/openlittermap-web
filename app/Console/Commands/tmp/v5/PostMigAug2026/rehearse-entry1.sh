#!/usr/bin/env bash
#
# Rehearsal loop for tag retirement entry 1 (other--plastic_bag).
#
# ONE ITERATION = restore -> migrate -> rebuild Redis -> capture -> apply -> verify -> capture.
#
# The restore is what makes this repeatable, and it is also why the middle steps exist: the
# .sql source of truth predates the retired_at/merged_into_id migration and carries no Redis
# state, so every iteration must re-run the migration and rebuild Redis or the run fails
# confusingly on a missing column and an empty object hash.
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
ITERATIONS="${1:-3}"
ARTEFACTS="storage/app/tag-retirement-snapshots/${ENTRY}"
WORKING_QUEUE="storage/app/rehearsal-queue.csv"

if [[ "${DB}" != olm_postmig_* && "${DB}" != *rehearsal* ]]; then
    echo "REFUSING: '${DB}' is not a rehearsal database." >&2
    exit 1
fi

if [[ -z "${DUMP:-}" ]]; then
    echo "REFUSING: set DUMP=/path/to/production.sql" >&2
    exit 1
fi

if [[ ! -f "${DUMP}" ]]; then
    echo "REFUSING: dump not found at ${DUMP}" >&2
    exit 1
fi

mysql_do() { mysql -u"${DB_USER}" -p"${DB_PASS}" "$@"; }

step() { printf '\n\033[1m── %s\033[0m\n' "$*"; }
fail() { printf '\033[31mFAILED: %s\033[0m\n' "$*" >&2; exit 1; }

for i in $(seq 1 "${ITERATIONS}"); do
    printf '\n\033[1;44m  ITERATION %s of %s  \033[0m\n' "${i}" "${ITERATIONS}"

    step "0. clear prior run state"
    # A crashed iteration leaves a snapshot behind. Its fingerprint would still match, so the
    # next iteration would silently RESUME rather than start clean — and a rehearsal that
    # resumes is not measuring what it claims to.
    rm -f "storage/app/migrate-tag/${ENTRY}.json" "storage/app/migrate-tag/${ENTRY}.lock"
    rm -f "${ARTEFACTS}/before.json" "${ARTEFACTS}/after.json"

    step "1. restore ${DB} from ${DUMP}"
    mysql_do -e "DROP DATABASE IF EXISTS \`${DB}\`; CREATE DATABASE \`${DB}\` CHARACTER SET utf8mb4;"
    mysql_do "${DB}" < "${DUMP}"

    step "2. migrate (retired_at / merged_into_id)"
    php artisan migrate --force --no-interaction

    step "3. flush + rebuild Redis"
    php artisan tinker --execute="\Illuminate\Support\Facades\Redis::command('FLUSHDB');"
    php artisan olm:redis:rebuild --no-flush --batch=2000 \
        || fail "redis rebuild did not complete"

    step "4-5. baseline assert + before capture"
    # The capture asserts 189,518 / 277,169 itself and refuses to write on mismatch.
    php artisan olm:tag-retirement-snapshot --entry="${ENTRY}" --label=before \
        || fail "before capture failed (baseline mismatch or reconciliation unreadable)"

    step "6. lifecycle + apply"
    # Work from a COPY of the approved list. --advance rewrites the CSV, so rehearsing against
    # the canonical file would leave it past MAPPING_APPROVED and make iteration 2 start from a
    # different state — non-deterministic, and it would mutate a reviewed artefact.
    cp readme/audit/TagRetirements-2026-08.csv "${WORKING_QUEUE}"

    Q="--queue=${WORKING_QUEUE}"

    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" || fail "dry run failed"
    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --advance=CODE_UPDATED --by=rehearsal \
        || fail "advance to CODE_UPDATED failed"
    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --advance=DRY_RUN_VERIFIED --by=rehearsal \
        --evidence="rehearsal iteration ${i}: dry run counts matched" \
        || fail "advance to DRY_RUN_VERIFIED failed"

    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --apply || fail "apply failed"

    step "7. verify"
    php artisan olm:migrate-tag --entry="${ENTRY}" "${Q}" --verify || fail "verify failed"

    step "8. after capture + diff"
    php artisan olm:tag-retirement-snapshot --entry="${ENTRY}" --label=after \
        || fail "after capture failed"

    php artisan olm:tag-retirement-snapshot --entry="${ENTRY}" --diff=before,after \
        | tee "/tmp/rehearsal-diff-${i}.txt"

    cp "${ARTEFACTS}/before.json" "/tmp/rehearsal-before-${i}.json"
    cp "${ARTEFACTS}/after.json" "/tmp/rehearsal-after-${i}.json"

    printf '\n  iteration %s hashes:\n' "${i}"
    shasum -a 256 "/tmp/rehearsal-before-${i}.json" "/tmp/rehearsal-after-${i}.json" \
        "/tmp/rehearsal-diff-${i}.txt" | sed 's/^/    /'
done

step "determinism across ${ITERATIONS} iterations"
for kind in before after diff; do
    ext=json; [[ "${kind}" == diff ]] && ext=txt
    hashes=$(for i in $(seq 1 "${ITERATIONS}"); do
        shasum -a 256 "/tmp/rehearsal-${kind}-${i}.${ext}" | cut -d' ' -f1
    done | sort -u)

    if [[ $(wc -l <<< "${hashes}") -eq 1 ]]; then
        printf '  %-7s IDENTICAL across %s runs  %s\n' "${kind}" "${ITERATIONS}" "${hashes}"
    else
        printf '  \033[31m%-7s DIVERGED:\033[0m\n%s\n' "${kind}" "${hashes}"
        exit 1
    fi
done
