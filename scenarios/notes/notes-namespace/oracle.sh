#!/usr/bin/env bash
set -euo pipefail

GIT_AUTHOR_DATE='@1700000100 +0000' \
GIT_COMMITTER_DATE='@1700000100 +0000' \
git notes --ref=review add -m 'Pitmaster review' HEAD
git notes --ref=review show HEAD > .note.txt
git rev-parse refs/notes/review > .note-ref.txt
git notes --ref=review list > .note-list.txt
