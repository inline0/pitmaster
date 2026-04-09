#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

cat > f.txt <<'EOF'
alpha
beta
gamma
EOF

git add f.txt
GIT_AUTHOR_NAME='Alice' \
GIT_AUTHOR_EMAIL='alice@example.com' \
GIT_AUTHOR_DATE='2024-01-01T00:00:00+0000' \
GIT_COMMITTER_NAME='Alice' \
GIT_COMMITTER_EMAIL='alice@example.com' \
GIT_COMMITTER_DATE='2024-01-01T00:00:00+0000' \
git commit -m base >/dev/null

cat > f.txt <<'EOF'
alpha
beta two
gamma
EOF

git add f.txt
GIT_AUTHOR_NAME='Bob' \
GIT_AUTHOR_EMAIL='bob@example.com' \
GIT_AUTHOR_DATE='2024-01-02T00:00:00+0000' \
GIT_COMMITTER_NAME='Bob' \
GIT_COMMITTER_EMAIL='bob@example.com' \
GIT_COMMITTER_DATE='2024-01-02T00:00:00+0000' \
git commit -m modify >/dev/null

cat > f.txt <<'EOF'
alpha
beta two
gamma
delta
EOF

git add f.txt
GIT_AUTHOR_NAME='Carol' \
GIT_AUTHOR_EMAIL='carol@example.com' \
GIT_AUTHOR_DATE='2024-01-03T00:00:00+0000' \
GIT_COMMITTER_NAME='Carol' \
GIT_COMMITTER_EMAIL='carol@example.com' \
GIT_COMMITTER_DATE='2024-01-03T00:00:00+0000' \
git commit -m append >/dev/null
