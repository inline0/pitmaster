#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

for i in 1 2 3 4 5 6; do
  python3 - "$i" <<'PY'
from pathlib import Path
import sys
base = "header\n" + "\n".join(f"line {i} base" for i in range(1, 301)) + "\n"
Path("file.txt").write_text(base + f"change {sys.argv[1]}\n")
PY
  git add file.txt
  GIT_AUTHOR_DATE="@170040000${i} +0000" \
  GIT_COMMITTER_DATE="@170040000${i} +0000" \
  git commit -m "commit ${i}" >/dev/null
done

git rev-list --all | git pack-objects --delta-base-offset --window=50 --depth=50 .git/objects/pack/codec-pack --revs >/dev/null
