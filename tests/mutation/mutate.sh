#!/bin/bash
# Mutation testing (SWEBOK Ch4, 3.4.3). Plants one small fault (a mutant) at a time in a
# source file, runs the unit test that covers that file, and restores the file afterwards.
# A mutant is KILLED when the test fails (good). It SURVIVES when the test still passes,
# which points to a check that is missing. Nothing is committed.
#
# Run from the root of the repository, with MySQL running and the seed data loaded:
#     bash tests/mutation/mutate.sh tests/mutation/tracking.txt     one list
#     bash tests/mutation/mutate.sh tests/mutation/*.txt            every list
# On XAMPP, say which PHP to use:
#     PHP=/Applications/XAMPP/bin/php bash tests/mutation/mutate.sh tests/mutation/*.txt
#
# Each list starts with two lines that name the file to change and the test to run,
#     # file: app/src/tracking.php
#     # test: tests/tracking_test.php
# followed by one mutant per line:   ID|what the fault is|perl substitution
#
# Added by Pichponleur Pen on 23 October 2026. dashboard.txt holds the mutants of the
# 17 October run in docs/test-log.md, so that run can now be repeated by anyone.

PHP=${PHP:-php}
[ $# -gt 0 ] || { echo "Usage: bash tests/mutation/mutate.sh tests/mutation/<list>.txt ..."; exit 1; }
[ -d app ] && [ -d tests ] || { echo "Run this from the root of the repository."; exit 1; }

all_killed=0; all_total=0; not_applied=0
for LIST in "$@"; do
  F=$(sed -n 's/^# file: *//p' "$LIST" | head -1)
  TEST=$(sed -n 's/^# test: *//p' "$LIST" | head -1)
  [ -f "$F" ] && [ -f "$TEST" ] || { echo "$LIST: cannot find '$F' or '$TEST'."; exit 1; }
  git diff --quiet -- "$F" || { echo "Commit or stash your changes to $F first."; exit 1; }
  "$PHP" "$TEST" > /dev/null 2>&1 || { echo "$TEST fails on the original code. Start MySQL and reload the seed data first."; exit 1; }

  ORIG=$(mktemp)
  cp "$F" "$ORIG"
  trap 'cp "$ORIG" "$F"; rm -f "$ORIG"' EXIT   # the file is restored even if the run is stopped

  echo "== $LIST: $F, checked by $TEST"
  killed=0; total=0
  while IFS='|' read -r id desc expr; do
    case "$id" in ''|'#'*) continue ;; esac
    cp "$ORIG" "$F"
    perl -0pi -e "$expr" "$F"
    if cmp -s "$F" "$ORIG"; then
      echo "$id  NOT APPLIED  $desc"; not_applied=$((not_applied + 1)); continue
    fi
    total=$((total + 1))
    if "$PHP" "$TEST" > /dev/null 2>&1; then
      echo "$id  SURVIVED     $desc"
    else
      echo "$id  KILLED       $desc"; killed=$((killed + 1))
    fi
  done < "$LIST"

  cp "$ORIG" "$F"; rm -f "$ORIG"; trap - EXIT
  echo "Mutation score: $killed of $total mutants killed"
  echo
  all_killed=$((all_killed + killed)); all_total=$((all_total + total))
done

[ $# -gt 1 ] && echo "All lists: $all_killed of $all_total mutants killed"
[ $not_applied -gt 0 ] && echo "$not_applied mutant(s) did not apply: the code has changed, so update the list."
[ $all_killed -eq $all_total ] && [ $not_applied -eq 0 ]
