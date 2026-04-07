#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

cp $f $fname 2>/dev/null || true
printf Z >>"$fname" 2>/dev/null || true
cp CRLF_mix_LF $fname 2>/dev/null || true
printf Z >>"$fname" 2>/dev/null || true
printf "${STR}BBB\001" >TeBi_127_S 2>/dev/null || true
printf "${STR}BBBB\001">TeBi_128_S 2>/dev/null || true
printf "${STR}BBB\032" >TeBi_127_E 2>/dev/null || true
printf "\032${STR}BBB" >TeBi_E_127 2>/dev/null || true
printf "${STR}BBBB\000">TeBi_128_N 2>/dev/null || true
printf "${STR}BBB\012">TeBi_128_L 2>/dev/null || true
printf "${STR}BBB\015">TeBi_127_C 2>/dev/null || true
printf "${STR}BB\015\012" >TeBi_126_CL 2>/dev/null || true
printf "${STR}BB\015\012\015" >TeBi_126_CLC 2>/dev/null || true
echo >.gitattributes 2>/dev/null || true
git checkout -b main 2>/dev/null || true
git add .gitattributes 2>/dev/null || true
git commit -m "add .gitattributes" . 2>/dev/null || true
printf "\$Id: 0000000000000000000000000000000000000000 \$\nLINEONE\nLINETWO\nLINETHREE"     >LF 2>/dev/null || true
printf "\$Id: 0000000000000000000000000000000000000000 \$\r\nLINEONE\r\nLINETWO\r\nLINETHREE" >CRLF 2>/dev/null || true
printf "\$Id: 0000000000000000000000000000000000000000 \$\nLINEONE\r\nLINETWO\nLINETHREE"   >CRLF_mix_LF 2>/dev/null || true
printf "\$Id: 0000000000000000000000000000000000000000 \$\nLINEONE\nLINETWO\rLINETHREE"     >LF_mix_CR 2>/dev/null || true
printf "\$Id: 0000000000000000000000000000000000000000 \$\r\nLINEONE\r\nLINETWO\rLINETHREE"   >CRLF_mix_CR 2>/dev/null || true
printf "\$Id: 0000000000000000000000000000000000000000 \$\r\nLINEONEQ\r\nLINETWO\r\nLINETHREE" | q_to_nul >CRLF_nul 2>/dev/null || true
printf "\$Id: 0000000000000000000000000000000000000000 \$\nLINEONEQ\nLINETWO\nLINETHREE" | q_to_nul >LF_nul 2>/dev/null || true
git commit -m "mixed line endings" 2>/dev/null || true
test_tick 2>/dev/null || true

true
