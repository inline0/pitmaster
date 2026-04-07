#!/bin/bash
# Git test framework shim for Pitmaster scenario extraction.
# Implements the subset of git's test-lib-functions.sh needed to
# create repos, without the full test harness.

# Deterministic timestamps
test_tick () {
	if test -z "${test_tick+set}"; then
		test_tick=1112911993
	else
		test_tick=$(($test_tick + 60))
	fi
	GIT_COMMITTER_DATE="$test_tick -0700"
	GIT_AUTHOR_DATE="$test_tick -0700"
	export GIT_COMMITTER_DATE GIT_AUTHOR_DATE
}

# Create a commit with optional file, message, and tag
test_commit () {
	local notick= echo=echo append= author= signoff= indir= tag=light
	while test $# != 0; do
		case "$1" in
		--notick) notick=yes ;;
		--printf) echo=printf ;;
		--append) append=yes ;;
		--author) author="$2"; shift ;;
		--signoff) signoff="$1" ;;
		--date) notick=yes; GIT_COMMITTER_DATE="$2"; GIT_AUTHOR_DATE="$2"; shift ;;
		-C) indir="$2"; shift ;;
		--no-tag) tag=none ;;
		--annotate) tag=annotate ;;
		*) break ;;
		esac
		shift
	done
	indir=${indir:+"$indir"/}
	local file="${2:-"$1.t"}"
	if test -n "$append"; then
		$echo "${3-$1}" >>"$indir$file"
	else
		$echo "${3-$1}" >"$indir$file"
	fi
	git ${indir:+ -C "$indir"} add -- "$file" &&
	if test -z "$notick"; then test_tick; fi &&
	git ${indir:+ -C "$indir"} commit ${author:+ --author "$author"} $signoff -m "$1" &&
	case "$tag" in
	none) ;;
	light) git ${indir:+ -C "$indir"} tag "${4:-$1}" ;;
	annotate) if test -z "$notick"; then test_tick; fi; git ${indir:+ -C "$indir"} tag -a -m "$1" "${4:-$1}" ;;
	esac
}

test_merge () {
	local label="$1"; shift
	test_tick
	git merge -m "$label" "$@" &&
	git tag "$label"
}

test_commit_bulk () {
	local message n=1
	while test $# != 0; do
		case "$1" in
		--start=*) n=${1#--start=} ;;
		--message=*) message=${1#--message=} ;;
		-C) shift ;;  # ignore dir
		--id=*) ;;
		--filename=*) ;;
		*) break ;;
		esac
		shift
	done
	local total="$1"
	while test "$n" -le "$total" 2>/dev/null; do
		echo "bulk commit $n" > "bulk-$n.t"
		git add "bulk-$n.t"
		test_tick
		git commit -m "${message:-commit $n}" 2>/dev/null || true
		n=$((n + 1))
	done
}

test_create_repo () {
	local repo="$1"
	mkdir -p "$repo"
	cd "$repo"
	git init .
	git config user.email "test@test.com"
	git config user.name "Test"
	cd ..
}

test_config () {
	git config "$@"
}

test_config_global () {
	git config --global "$@"
}

test_unconfig () {
	git config --unset "$@" 2>/dev/null || true
}

test_chmod () {
	chmod "$1" "$2"
	git update-index --chmod="$1" "$2" 2>/dev/null || true
}

test_write_lines () {
	printf '%s\n' "$@"
}

test_seq () {
	seq "$@"
}

test_oid () {
	# Return a placeholder OID
	echo "0000000000000000000000000000000000000000"
}

test_oid_cache () {
	true
}

test_when_finished () {
	# Cleanup hooks - skip in extraction
	true
}

test_set_prereq () { true; }
test_have_prereq () { return 0; }
test_lazy_prereq () { true; }

test_expect_success () {
	# Just run the test body
	if test "$#" -eq 3; then
		# test_expect_success PREREQ 'description' 'body'
		eval "$3" 2>/dev/null || true
	elif test "$#" -eq 2; then
		# test_expect_success 'description' 'body'
		eval "$2" 2>/dev/null || true
	fi
}

test_expect_failure () {
	# Run but ignore failures
	if test "$#" -eq 3; then
		eval "$3" 2>/dev/null || true
	elif test "$#" -eq 2; then
		eval "$2" 2>/dev/null || true
	fi
}

test_expect_code () {
	shift  # skip expected code
	test_expect_success "$@"
}

# Assertion stubs (no-ops in extraction mode)
test_cmp () { true; }
test_cmp_rev () { true; }
test_cmp_config () { true; }
test_must_fail () { "$@" 2>/dev/null || true; }
test_must_be_empty () { true; }
test_might_fail () { "$@" 2>/dev/null || true; }
test_grep () { true; }
test_line_count () { true; }
test_stdout_line_count () { true; }
test_path_is_file () { true; }
test_path_is_dir () { true; }
test_path_is_missing () { true; }
test_path_exists () { true; }
test_path_is_symlink () { true; }
test_file_not_empty () { true; }
test_dir_is_empty () { true; }
test_pause () { true; }
test_done () { true; }
test_i18ngrep () { true; }
test_i18ncmp () { true; }
test_all_match () { true; }

write_script () {
	local name="$1"; shift
	cat >"$name"
	chmod +x "$name"
}

test_hook () {
	local name="$1"; shift
	mkdir -p .git/hooks
	write_script ".git/hooks/$name" "$@"
}

sane_unset () {
	unset "$@" 2>/dev/null || true
}

# Stub out test description (sourced at top of each test)
test_description=""

# Protocol safety for local clones
export GIT_CONFIG_SYSTEM=/dev/null
