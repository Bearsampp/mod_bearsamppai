# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

* Replace the model text suggestions with a visible dropdown and an explicit custom-model field, preserving existing custom IDs.

## [2026.10.17.1] - 2026-10-17

### Fixed

* Retry empty successful AI responses with the configured fallback model, log response-shape diagnostics, upgrade the old 512-token default to 2048 and keep answers focused.

## [2026.10.16.4] - 2026-10-16

### Changed

* Update version to 2026.10.16.2 [skip ci] ([3229713](https://github.com/Bearsampp/mod_bearsamppai/commit/3229713))
* Update stats downloads snapshot ([4d4fe56](https://github.com/Bearsampp/mod_bearsamppai/commit/4d4fe56))
* Improve AI API response parsing with robust content extraction ([d3897f2](https://github.com/Bearsampp/mod_bearsamppai/commit/d3897f2))
* Merge remote-tracking branch 'origin/main' ([1ff534f](https://github.com/Bearsampp/mod_bearsamppai/commit/1ff534f))
* Update version to 2026.10.16.3 [skip ci] ([cae8529](https://github.com/Bearsampp/mod_bearsamppai/commit/cae8529))
* Migrate to OpenCode inference endpoint and update default model to deepseek-v4.1-flash ([8c9240e](https://github.com/Bearsampp/mod_bearsamppai/commit/8c9240e))
* Merge remote-tracking branch 'origin/main' ([a5619aa](https://github.com/Bearsampp/mod_bearsamppai/commit/a5619aa))
