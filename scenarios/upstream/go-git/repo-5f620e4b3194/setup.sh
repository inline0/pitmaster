#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-5f620e4b3194c0c4a77fbd17f501030a441f54d4
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-5f620e4b3194c0c4a77fbd17f501030a441f54d4.tgz" -C .git
git checkout -- . 2>/dev/null || true
