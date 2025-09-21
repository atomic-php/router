# Changelog

All notable changes to this project are documented in this file.

## v1.0.0 — Initial release

- High-performance PSR-7/15 router with compile-time optimization
- Static and dynamic routes with named parameters and regex constraints
- Zero-overhead compiled dispatcher cached until routes change
- Optional 404 and 405 handlers; exceptions in `Atomic\\Router\\Exceptions`
- UTF‑8 path support with percent-decoded segment matching
- Route-aware middleware pattern:
  - `RouterMatchMiddleware` (match + inject params/handler)
  - `RouteDispatchMiddleware` (dispatch matched handler)
- Benchmarks, 100% static analysis (Psalm) clean, CS clean
- GitHub Actions CI: tests, coverage, Psalm, CS check

