#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir -p src docs bin
cat > README.md <<'EOF'
hello root
bye
EOF
cat > src/feature.txt <<'EOF'
first line
hello nested
EOF
cat > docs/guide.txt <<'EOF'
nothing here
EOF
python3 - <<'PY'
from pathlib import Path
Path("bin/data.bin").write_bytes(b"hello\x00binary\n")
PY

git add .
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m initial >/dev/null
