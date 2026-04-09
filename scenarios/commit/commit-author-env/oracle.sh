#!/usr/bin/env bash
set -euo pipefail

cat > .message <<'EOF'
Subject line

Body paragraph
Signed-off-by: Trailer <trailer@example.com>
EOF

GIT_AUTHOR_NAME='Alice Author' \
GIT_AUTHOR_EMAIL='alice@example.com' \
GIT_AUTHOR_DATE='@1712563200 +0200' \
GIT_COMMITTER_NAME='Chris Committer' \
GIT_COMMITTER_EMAIL='chris@example.com' \
GIT_COMMITTER_DATE='@1712566800 +0200' \
git commit --quiet -F .message

git cat-file -p HEAD > .commit-state
