# Changelog

All notable changes to `auth` will be documented in this file.

## v0.1.3 - 2026-02-26

**Full Changelog**: https://github.com/pictastudio/auth/compare/v0.1.2...v0.1.3

## v0.1.2 - 2026-02-26

**Full Changelog**: https://github.com/pictastudio/auth/compare/v0.1.1...v0.1.2

## v0.1.1 - 2026-02-26

### What's Changed

- code cleanup

**Full Changelog**: https://github.com/pictastudio/auth/compare/v0.1.0...v0.1.1

## v0.1.0 - 2026-02-25

### What's in this release

Initial release of the Picta Auth Laravel package with cookie-based authentication, configurable password rules, personal access tokens, and Bruno API collection.


---

### Features

- **v1 auth implementation** - Core authentication flow and routes
- **Cookie-based authentication** - Session/cookie auth support with documentation
- **Custom install command** - Dedicated Artisan command for package installation
- **Personal access tokens** - `personal_access_tokens` table and token handling
- **Configurable password rules** - Password validation rules exposed via config
- **Config rename** - Config file renamed from `auth` to `picta-auth` for namespacing
- **Bruno requests** - Publishable Bruno API request collection for testing
- **Auth route tests** - Test coverage for authentication routes


---

### Fixes

- Fix tests


---

### Dependencies

- **spatie/laravel-permission** - Updated from ^6.0 to ^7.2
- **stefanzweifel/git-auto-commit-action** - Bumped from 5 to 7 (GitHub Actions)

**Full Changelog**: https://github.com/pictastudio/auth/commits/v0.1.0
