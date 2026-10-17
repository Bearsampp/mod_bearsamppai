# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

* Replace model text suggestions with a visible dropdown and custom model ID field, preserving existing provider-specific model IDs.

## [2026.10.17] - 2026-10-17

### Fixed

* Retry empty successful AI responses with the configured fallback model, log response-shape diagnostics, upgrade the old 512-token default to 2048 and keep answers focused.

### Changed

* Update version to 2026.10.16.3 [skip ci] ([cae8529](https://github.com/Bearsampp/mod_bearsamppai/commit/cae8529))
* Migrate to OpenCode inference endpoint and update default model to deepseek-v4.1-flash ([8c9240e](https://github.com/Bearsampp/mod_bearsamppai/commit/8c9240e))
* Merge remote-tracking branch 'origin/main' ([a5619aa](https://github.com/Bearsampp/mod_bearsamppai/commit/a5619aa))
* Update version to 2026.10.16.4 [skip ci] ([88d503d](https://github.com/Bearsampp/mod_bearsamppai/commit/88d503d))
* Retry empty AI responses and expand answer budget ([c684684](https://github.com/Bearsampp/mod_bearsamppai/commit/c684684))
* Merge remote-tracking branch 'origin/main' ([3785498](https://github.com/Bearsampp/mod_bearsamppai/commit/3785498))
