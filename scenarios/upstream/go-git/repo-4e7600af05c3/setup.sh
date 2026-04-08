#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-4e7600af05c3356e8b142263e127b76f010facfc
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-4e7600af05c3356e8b142263e127b76f010facfc.tgz" -C .git
git checkout -- . 2>/dev/null || true
