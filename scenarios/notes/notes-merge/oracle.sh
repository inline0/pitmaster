#!/usr/bin/env bash
set -euo pipefail

latest="$(git rev-parse HEAD)"
initial="$(git rev-parse HEAD~1)"
git notes add -m 'Main note' "$initial"
git notes --ref=review add -m 'Review note' "$latest"
git notes merge review >/dev/null
git notes list > .notes-list.txt
git notes show "$latest" > .latest-note.txt
