#!/bin/bash
set -e

head="$(git rev-parse HEAD)"
printf "%s\n" "$head" > .git/shallow
git rev-parse --is-shallow-repository
cat .git/shallow
