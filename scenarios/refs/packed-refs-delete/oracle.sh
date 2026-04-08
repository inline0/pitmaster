#!/bin/bash
set -e

git pack-refs --all
git branch -D feature
git tag -d v1.0
git tag -d v1.1
