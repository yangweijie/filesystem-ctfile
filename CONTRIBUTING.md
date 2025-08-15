# Contributing to CTFile Flysystem Adapter

Thank you for considering contributing to the CTFile Flysystem Adapter! This document provides guidelines and information for contributors.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code.

## How to Contribute

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When creating a bug report, include:

- A clear and descriptive title
- Steps to reproduce the issue
- Expected behavior
- Actual behavior
- Environment details (PHP version, OS, etc.)
- Code samples if applicable

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- A clear and descriptive title
- A detailed description of the proposed enhancement
- Use cases and benefits
- Possible implementation approaches

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add or update tests as needed
5. Ensure all tests pass
6. Update documentation if necessary
7. Commit your changes (`git commit -m 'Add amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- CTFile account for integration testing

### Installation

```bash
git clone https://github.com/yangweijie/filesystem-ctlife.git
cd filesystem-ctlife
composer install
```

### Running Tests

```bash
# Unit tests
composer test

# Unit tests with coverage
composer test-coverage

# Integration tests (requires CTFile credentials)
export CTFILE_SESSION="your-session-token"
export CTFILE_APP_ID="your-app-id"
composer test-integration

# Performance tests
composer test-performance

# All tests
composer test-all
```

### Code Style

This project follows PSR-12 coding standards. Use PHP CS Fixer to maintain consistency:

```bash
composer cs-fix
```

### Static Analysis

Run PHPStan for static analysis:

```bash
composer analyse
```

## Project Structure

```
src/
├── CTFileAdapter.php          # Main adapter class
├── CTFileClient.php           # API client
├── CTFileConfig.php           # Configuration class
├── PathMapper.php             # Path mapping utility
├── Exceptions/                # Exception classes
│   ├── CTFileException.php
│   ├── ApiException.php
│   ├── AuthenticationException.php
│   ├── NetworkException.php
│   └── RateLimitException.php
└── Support/                   # Helper classes
    ├── FileInfo.php
    └── UploadHelper.php

tests/
├── Unit/                      # Unit tests
├── Integration/               # Integration tests
├── Mock/                      # Mock tests
└── Performance/               # Performance tests
```

## Coding Guidelines

### General Principles

- Write clean, readable, and maintainable code
- Follow SOLID principles
- Use meaningful variable and method names
- Add appropriate comments for complex logic
- Ensure backward compatibility when possible

### Testing Guidelines

- Write tests for all new features
- Maintain or improve test coverage
- Use descriptive test names
- Test both success and failure scenarios
- Mock external dependencies in unit tests

### Documentation

- Update README.md for new features
- Add PHPDoc comments for all public methods
- Include code examples in documentation
- Update CHANGELOG.md for notable changes

## Architecture Guidelines

### Exception Handling

- Use specific exception types for different error conditions
- Provide meaningful error messages
- Include context information in exceptions
- Map CTFile errors to appropriate Flysystem exceptions

### Performance Considerations

- Implement efficient caching strategies
- Minimize API calls where possible
- Use streams for large file operations
- Consider memory usage in operations

### API Integration

- Handle rate limiting gracefully
- Implement retry mechanisms for transient failures
- Validate API responses thoroughly
- Use appropriate HTTP methods and headers

## Release Process

1. Update version in `composer.json`
2. Update `CHANGELOG.md`
3. Create a release tag
4. Publish to Packagist

## Getting Help

- Check existing issues and documentation
- Ask questions in GitHub Discussions
- Contact maintainers for complex issues

## Recognition

Contributors will be recognized in:
- README.md credits section
- CHANGELOG.md for significant contributions
- GitHub contributors page

Thank you for contributing to make this project better!
