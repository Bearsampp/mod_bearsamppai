# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2026.09.30.5] - 2026-09-30

### Added

* Add KB context diagnostics (log what sources fitted, counts, and sample labels) and make logging best-effort to avoid breaking chat on CLI/early errors. ([6697e15](https://github.com/Bearsampp/mod_bearsamppai/commit/6697e15))

### Changed

* Update version to 2026.09.30.3 [skip ci] ([94aa06e](https://github.com/Bearsampp/mod_bearsamppai/commit/94aa06e))
* Update version to 2026.09.30.4 [skip ci] ([480ecaf](https://github.com/Bearsampp/mod_bearsamppai/commit/480ecaf))
* Load FAQ first so targeted KB entries can't be starved by bulk articles. Account for the part separator in the character budget. Clarify the KB source order. ([6130de2](https://github.com/Bearsampp/mod_bearsamppai/commit/6130de2))

