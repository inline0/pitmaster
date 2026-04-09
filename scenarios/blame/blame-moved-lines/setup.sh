#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

cat > f.txt <<'EOF'
one
two
three
four
EOF

git add f.txt
GIT_AUTHOR_NAME='Alice' \
GIT_AUTHOR_EMAIL='alice@example.com' \
GIT_AUTHOR_DATE='2024-03-01T00:00:00+0000' \
GIT_COMMITTER_NAME='Alice' \
GIT_COMMITTER_EMAIL='alice@example.com' \
GIT_COMMITTER_DATE='2024-03-01T00:00:00+0000' \
git commit -m base >/dev/null

cat > f.txt <<'EOF'
three
four
one
two
EOF

git add f.txt
GIT_AUTHOR_NAME='Bob' \
GIT_AUTHOR_EMAIL='bob@example.com' \
GIT_AUTHOR_DATE='2024-03-02T00:00:00+0000' \
GIT_COMMITTER_NAME='Bob' \
GIT_COMMITTER_EMAIL='bob@example.com' \
GIT_COMMITTER_DATE='2024-03-02T00:00:00+0000' \
git commit -m move >/dev/null
