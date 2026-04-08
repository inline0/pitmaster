#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-02T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-02T00:00:00+0000"

cat > .gitattributes <<'EOF'
*.txt text eol=lf
*.md diff=markdown
docs/* custom
*.dat -diff
EOF

mkdir -p docs
printf "txt\n" > readme.txt
printf "md\n" > guide.md
printf "bin\n" > docs/file.bin
printf "dat\n" > archive.dat

git add .gitattributes readme.txt guide.md docs/file.bin archive.dat
git commit -m "Add attributes"
