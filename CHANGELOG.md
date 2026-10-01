# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2026.10.01] - 2026-10-01

### Changed

* Update version to 2026.09.30.6 [skip ci] ([c371768](https://github.com/Bearsampp/mod_bearsamppai/commit/c371768))
* Cache the assembled knowledge context, keyed on params plus a MAX(modified)/COUNT fingerprint of the content in scope, so publishing or editing content invalidates it automatically. Raise the context ceiling to 200000 and clamp to it. ([61c0ec5](https://github.com/Bearsampp/mod_bearsamppai/commit/61c0ec5))
* Update version to 2026.09.30.7 [skip ci] ([848e2b7](https://github.com/Bearsampp/mod_bearsamppai/commit/848e2b7))
* Stop a context cache failure from failing the chat ([0a2b5c5](https://github.com/Bearsampp/mod_bearsamppai/commit/0a2b5c5))

