#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-22T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-22T00:00:01+0000"

mkdir -p src/Nested
cat > src/Service.php <<'EOF'
<?php
return 'service';
EOF

cat > src/Nested/Helper.php <<'EOF'
<?php
return 'helper';
EOF

git add src
git commit -m initial >/dev/null
