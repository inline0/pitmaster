#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir -p src docs
cat > README.md <<'EOF'
needle in root
EOF
cat > src/feature.txt <<'EOF'
alpha
needle in src
EOF
cat > docs/guide.txt <<'EOF'
nothing here
needle in docs
EOF

git add .
GIT_AUTHOR_DATE='2024-01-01T00:00:00+0000' \
GIT_COMMITTER_DATE='2024-01-01T00:00:00+0000' \
git commit -m initial >/dev/null
