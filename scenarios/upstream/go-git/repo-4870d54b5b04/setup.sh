#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-4870d54b5b04e43da8cf99ceec179d9675494af8
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-4870d54b5b04e43da8cf99ceec179d9675494af8.tgz" -C .git
git checkout -- . 2>/dev/null || true
