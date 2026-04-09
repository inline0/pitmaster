#!/usr/bin/env bash
set -euo pipefail

mkdir -p .git/hooks
cat > .git/hooks/pre-commit <<'EOF'
#!/bin/sh
echo pre-commit >> .hook-log
EOF
cat > .git/hooks/prepare-commit-msg <<'EOF'
#!/bin/sh
echo "prepare-commit-msg:${2:-}" >> .hook-log
printf '\nPrepared-by: hook\n' >> "$1"
EOF
cat > .git/hooks/commit-msg <<'EOF'
#!/bin/sh
echo commit-msg >> .hook-log
grep -q '^Prepared-by: hook$' "$1"
EOF
cat > .git/hooks/post-commit <<'EOF'
#!/bin/sh
echo post-commit >> .hook-log
EOF
chmod +x .git/hooks/pre-commit .git/hooks/prepare-commit-msg .git/hooks/commit-msg .git/hooks/post-commit

GIT_AUTHOR_DATE='@1712563200 +0200' \
GIT_COMMITTER_DATE='@1712566800 +0200' \
git commit --quiet -m 'Hook subject'

{
    printf '[log]\n'
    cat .hook-log
    printf '[message]\n'
    git log -1 --format=%B
} > .hook-state
