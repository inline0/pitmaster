#!/usr/bin/env bash
set -euo pipefail

linked="../linked-worktree-$$"
git worktree add -b linked "$linked" >/dev/null
git notes add -m 'Visible everywhere' HEAD
target=$(git rev-parse main)
git -C "$linked" notes show "$target" > .linked-note.txt
