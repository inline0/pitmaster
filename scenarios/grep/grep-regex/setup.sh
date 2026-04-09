#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir -p src docs
cat > README.md <<'EOF'
Hello root
bye
EOF
cat > src/feature.txt <<'EOF'
first line
HELLO nested
EOF
cat > docs/guide.txt <<'EOF'
helper text
EOF

git add .
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m initial >/dev/null
