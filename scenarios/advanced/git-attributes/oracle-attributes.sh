#!/bin/bash
set -e

git check-attr text eol -- readme.txt
git check-attr diff -- guide.md
git check-attr custom -- docs/file.bin
git check-attr diff -- archive.dat
