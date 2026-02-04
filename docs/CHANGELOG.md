# Changelog

All notable changes to `laravel-api-guardian` will be documented in this file.

## Unreleased

### Added
- **GraphQL Error Format Support**: Complete implementation of GraphQL error specification
  - New `GraphQLFormatter` class with full spec compliance
  - Automatic error categorization (authentication, authorization, validation, etc.)
  - Support for GraphQL-specific fields: `locations`, `path`, and `extensions`
  - Rich error extensions with codes, categories, suggestions, and debug info
  - Multiple validation error handling
  - Custom method support for `getLocations()` and `getPath()`
- Comprehensive GraphQL documentation (`docs/GRAPHQL.md`)
- GraphQL usage examples (`examples/graphql_usage.php`)
- 15 new test cases for GraphQL formatter

### Changed
- Updated `ApiGuardian` class to support 'graphql' format resolver
- Updated README.md with GraphQL format examples and configuration
- Enhanced test suite to include GraphQL formatter tests

## 1.0.0 - 2026-01-31

- Initial release
- Multiple error format support (JSend, RFC 7807, JSON:API)
- Fluent exception API
- Enhanced validation errors
- Development mode with debugging features
- Production mode with security features
- Artisan commands for error management
- Auto-documentation generation
- Middleware for format negotiation
- Comprehensive test suite
