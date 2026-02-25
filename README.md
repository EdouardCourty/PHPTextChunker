# Template Bundle

A Symfony bundle template for rapid bundle development.

## Requirements

- PHP 8.3+
- Symfony 7.0+

## Installation

```bash
composer install
```

## Getting Started

For AI-assisted development and project setup guidance, see [AGENTS.md](AGENTS.md). This file contains:
- First launch instructions
- Architecture overview and design patterns
- Development guidelines and best practices
- Testing strategies

## Development Tools

This template comes with essential development tools pre-configured:

- **PHPUnit 12**: Testing framework with Unit, Integration, and Functional test suites
- **PHPStan 2**: Static analysis for code quality
- **PHP CS Fixer 3**: Code style fixing and checking

## Testing

The bundle includes a test kernel (`TestKernel`) for functional and integration testing.

Run all tests:
```bash
composer test
```

Run specific test suites:
```bash
composer test-unit          # Unit tests only
composer test-integration   # Integration tests
composer test-functional    # Functional tests
```

## Quality Assurance

```bash
composer phpstan    # Static analysis
composer cs-check   # Check code style
composer cs-fix     # Fix code style
composer qa         # Run all QA checks
```

## License

MIT
