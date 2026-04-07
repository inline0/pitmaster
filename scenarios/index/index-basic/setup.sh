#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > hello.txt <<'EOF'
hello world
EOF

mkdir -p src
cat > src/main.php <<'EOF'
<?php
echo "Hello from Pitmaster";
EOF

cat > README <<'EOF'
A simple test repository.
EOF

git add hello.txt src/main.php README
git commit -m "Initial commit with three files"
