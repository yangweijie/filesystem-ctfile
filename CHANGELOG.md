# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial implementation of CtFileAdapter for Flysystem integration
- Core file operations (read, write, delete, exists)
- Directory operations (create, delete, list, exists)
- Configuration management with validation
- Error handling with custom exception hierarchy
- Retry mechanism for failed operations
- Caching support for metadata and directory listings
- Comprehensive test suite with unit and integration tests
- Static analysis with PHPStan
- Code style enforcement with PHP CS Fixer
- Continuous integration with GitHub Actions
- Complete API documentation
- Usage examples and troubleshooting guide

### Features
- **Flysystem Compatibility**: Full implementation of Flysystem FilesystemAdapter interface
- **ctFile Integration**: Seamless integration with ctFile functionality
- **Error Handling**: Comprehensive error handling with detailed exception messages
- **Caching**: Configurable caching layer for improved performance
- **Retry Logic**: Automatic retry mechanism for transient failures
- **Path Normalization**: Robust path handling and validation
- **Metadata Mapping**: Conversion between ctFile and Flysystem metadata formats
- **Configuration Validation**: Strict validation of configuration parameters
- **Logging Support**: PSR-3 compatible logging integration
- **Testing**: Extensive test coverage with mock server for isolated testing

### Technical Details
- **PHP Version**: Requires PHP 8.1 or higher
- **Dependencies**: 
  - league/flysystem ^3.0
  - psr/log ^3.0
  - psr/simple-cache ^3.0
- **Development Dependencies**:
  - pestphp/pest ^2.0
  - mockery/mockery ^1.5
  - phpstan/phpstan ^1.10
  - friendsofphp/php-cs-fixer ^3.0

## [1.0.0] - TBD

### Added
- Initial stable release
- Complete Flysystem adapter implementation
- Production-ready ctFile integration
- Comprehensive documentation
- Full test coverage
- Performance optimizations

### Breaking Changes
- None (initial release)

### Migration Guide
- This is the initial release, no migration required

### Security
- Secure credential handling
- Path traversal protection
- Input validation and sanitization
- Safe error message handling

---

## Release Process

1. Update version in `composer.json`
2. Update this CHANGELOG.md with release notes
3. Create a git tag with the version number
4. Push the tag to trigger release automation
5. Publish to Packagist (if not automated)

## Version Numbering

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

## Support

- **Current Version**: Unreleased (development)
- **Supported PHP Versions**: 8.1, 8.2, 8.3+
- **Supported Flysystem Versions**: 3.x

For support and questions, please:
1. Check the documentation in the `docs/` directory
2. Review existing issues on GitHub
3. Create a new issue if needed