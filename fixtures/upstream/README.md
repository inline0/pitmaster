# Upstream Fixtures

This directory vendors the upstream fixture trees that Pitmaster's imported oracle scenarios depend on.

The goal is simple: `./bin/test-regression` must be runnable from a fresh checkout without hidden machine-local fixture clones under `/tmp` or `/private/tmp`.

Current vendored sources:

- `dulwich/testdata` from `https://github.com/jelmer/dulwich`
- `libgit2/tests/resources` from `https://github.com/libgit2/libgit2`
- `go-git/data` from `https://github.com/go-git/go-git-fixtures`
- `jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources` from `https://github.com/eclipse-jgit/jgit`
- `isomorphic-git/__tests__/__fixtures__` from `https://github.com/isomorphic-git/isomorphic-git`
- `git/t` from `https://github.com/git/git`
- `gitpython/test` from `https://github.com/gitpython-developers/GitPython`

The imported scenario setup scripts resolve these files through `PITMASTER_ROOT`, so the corpus stays portable across machines and CI environments.
