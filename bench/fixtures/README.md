# Benchmark Fixtures

Benchmark fixture repositories are generated deterministically under `bench/fixtures/repos`.

Rules:

- definitions live in committed PHP fixture builders under `bench/lib/FixtureBuilder.php`
- generated repos stay repo-local
- no `/tmp` dependency
- no public network dependency
- the same fixture name should rebuild to the same Git history and metadata

These fixtures are inputs for the benchmark harness, not correctness fixtures for the oracle system.
