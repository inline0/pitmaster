#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

mkdir -p src lib

cat > src/main.php <<'EOF'
<?php
echo "Hello from Pitmaster";
EOF

cat > README.md <<'EOF'
# Pitmaster Test
A simple test repository for index round-trip verification.
EOF

cat > lib/utils.php <<'EOF'
<?php
function helper(): string {
    return "utility";
}
EOF

git add src/main.php README.md lib/utils.php
git commit -m "Initial commit with three files"
