#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add F 2>/dev/null || true
git update-ref HEAD $C 2>/dev/null || true
git tag C0 2>/dev/null || true
echo UTF-16 >F
echo "UTF-8 characters" >F
git commit -a -F "$HOME/invalid" 2>"$HOME"/stderr 2>/dev/null || true
echo "UTF-8 overlong" >F
git commit -a -F "$HOME/invalid" 2>"$HOME"/stderr 2>/dev/null || true
echo "UTF-8 non-character 1" >F
git commit -a -F "$HOME/invalid" 2>"$HOME"/stderr 2>/dev/null || true
echo "UTF-8 non-character 2." >F
git commit -a -F "$HOME/invalid" 2>"$HOME"/stderr 2>/dev/null || true
git config i18n.commitencoding $H 2>/dev/null || true
git checkout -b $H C0 2>/dev/null || true
echo $H >F
git commit -a -F "$TEST_DIRECTORY"/t3900/$H.txt 2>/dev/null || true
git config --unset-all i18n.commitencoding 2>/dev/null || true
git config i18n.commitencoding UTF-8 2>/dev/null || true
git config --unset-all i18n.commitencoding 2>/dev/null || true
git config i18n.commitencoding '$H' 2>/dev/null || true
git config i18n.logoutputencoding UTF-8 2>/dev/null || true
git config i18n.logoutputencoding $J 2>/dev/null || true
git config i18n.commitencoding $H 2>/dev/null || true
git checkout -b $H-$flag C0 2>/dev/null || true
echo $H >>F
git commit -a -F "$TEST_DIRECTORY"/t3900/$H.txt 2>/dev/null || true
echo intermediate stuff >>G
git add G 2>/dev/null || true
git commit -a -m "intermediate commit" 2>/dev/null || true
echo $H $flag >>F
git commit -a --$flag HEAD~1 2>/dev/null || true
git config --unset-all i18n.commitencoding 2>/dev/null || true
git checkout -b $flag-$old-$new C0 2>/dev/null || true
git config i18n.commitencoding $old 2>/dev/null || true
echo $old >>F
git commit -a -F "$TEST_DIRECTORY"/t3900/$msg 2>/dev/null || true
echo intermediate stuff >>G
git add G 2>/dev/null || true
git commit -a -m "intermediate commit" 2>/dev/null || true
git config i18n.commitencoding $new 2>/dev/null || true
echo $new-$flag >>F
git commit -a --$flag HEAD^ 2>/dev/null || true
