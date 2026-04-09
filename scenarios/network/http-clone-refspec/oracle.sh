#!/usr/bin/env bash
set -euo pipefail

url="$(cat .remote-url)"
git -C source remote set-url origin "$(pwd)/projects/remote.git"

git clone "$url" git-clone >/dev/null 2>&1
git -C git-clone config remote.origin.fetch '+refs/heads/main:refs/remotes/origin/main'

git -C source config user.email test@pitmaster.dev
git -C source config user.name "Test User"
cat > source/main.txt <<'EOF'
main branch
EOF
git -C source add main.txt
git -C source commit -m main-update >/dev/null
git -C source push origin main >/dev/null
git -C source checkout -b feature >/dev/null
cat > source/feature.txt <<'EOF'
feature branch
EOF
git -C source add feature.txt
git -C source commit -m feature-update >/dev/null
git -C source push origin feature >/dev/null
git -C source checkout main >/dev/null

git -C git-clone fetch origin >/dev/null

{
    printf 'remote.origin.fetch=%s\n' "$(git -C git-clone config --get remote.origin.fetch)"
    printf 'branch.main.remote=%s\n' "$(git -C git-clone config --get branch.main.remote)"
    printf 'branch.main.merge=%s\n' "$(git -C git-clone config --get branch.main.merge)"
    printf 'origin.main=%s\n' "$(git -C git-clone rev-parse refs/remotes/origin/main)"
    if [ -f git-clone/.git/refs/remotes/origin/feature ]; then
        printf 'origin.feature=present\n'
    else
        printf 'origin.feature=absent\n'
    fi
} > .clone-refspec-state
