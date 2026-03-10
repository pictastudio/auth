# Changelog

All notable changes to `auth` will be documented in this file.

## v0.1.7 - 2026-03-10

### What's Changed

#### Docs and Tooling

- **Flatten Bruno auth request paths** - move the published auth request files from `bruno/auth/auth/` to `bruno/auth/`
- **Simplify collection layout** - keep the auth requests alongside `bruno.json` and the environment folder for a cleaner published structure

**Full Changelog**: https://github.com/pictastudio/auth/compare/v0.1.6...v0.1.7

## v0.1.6 - 2026-03-09

### What's Changed

#### Features

- **Merge auth config recursively** - nested `picta-auth` application config now overrides package defaults without replacing sibling keys
- **Namespace publish tags** - config and Bruno publish groups now use `picta-auth-config` and `picta-auth-bruno`
- **Normalize Sanctum prefix trimming** - use `mb_trim` when syncing the configured auth route prefix into Sanctum

#### Docs

- **Update release notes** - document the recursive config merge and publish tag renames for this patch release

**Full Changelog**: https://github.com/pictastudio/auth/compare/v0.1.5...v0.1.6

## v0.1.5 - 2026-03-05

### What's Changed

#### Features

- **Add registration endpoint** - introduce `POST /api/auth/register` with validation, user creation, and optional verification email dispatch
- **Support both auth modes on register** - registration now mirrors login behavior, defaulting to cookie auth for stateful frontend requests and token auth otherwise, with `issue_token` and `token_name` overrides

#### Tests

- **Expand auth route coverage** - add register route tests for validation, duplicate emails, model/guard errors, cookie mode, token mode, and forced token issuance

#### Docs

- **Document registration flow** - update README and add a Bruno request for `/register`

**Full Changelog**: https://github.com/pictastudio/auth/compare/v0.1.4...v0.1.5

## v0.1.4 - 2026-03-03

put sanctum/csrf-cookie under api/auth/csrf-cookie

**Full Changelog**: https://github.com/pictastudio/auth/compare/v0.1.3...v0.1.4

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
