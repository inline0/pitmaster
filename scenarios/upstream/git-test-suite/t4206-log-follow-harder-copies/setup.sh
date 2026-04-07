#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
printf "%s\n" A B C D E F G H I J K L M N O P Q R S T U V W X Y Z >z
git add z 2>/dev/null || true
git commit -m "Initial" 2>/dev/null || true
echo lame >somefile
git add z somefile 2>/dev/null || true
git commit -m "Rewrite z, introduce lame somefile" 2>/dev/null || true
echo Content >somefile
git add somefile 2>/dev/null || true
git commit -m "Rewrite somefile" 2>/dev/null || true
