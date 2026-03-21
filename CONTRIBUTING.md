# Contributing to PMPro Magic Levels

Thank you for considering contributing to PMPro Magic Levels!

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in [Issues](https://github.com/raphaelsuzuki/pmpro-magic-levels/issues)
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - WordPress, PHP, and PMPro versions
   - Any error messages

### Suggesting Features

1. Check [existing feature requests](https://github.com/raphaelsuzuki/pmpro-magic-levels/issues?q=is%3Aissue+label%3Aenhancement)
2. Create a new issue with:
   - Clear use case
   - Expected behavior
   - Why this would be useful

### Code Contributions

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes following our coding standards
4. Test thoroughly
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Commit and Release Policy

This repository enforces [Conventional Commits](https://www.conventionalcommits.org/) and [Semantic Versioning](https://semver.org/).

- Pull request titles must use Conventional Commits format.
- Individual commits in pull requests must use Conventional Commits format.
- Releases are managed by release-please from conventional commit history.

Use these commit/PR title types:

- `feat`: New features (minor version bump)
- `fix`: Bug fixes (patch version bump)
- `docs`: Documentation only changes
- `style`: Formatting and style-only changes
- `refactor`: Code changes that do not fix a bug or add a feature
- `perf`: Performance improvements
- `test`: Test-only changes
- `build`: Build/dependency/tooling changes
- `ci`: CI/CD workflow changes
- `chore`: Maintenance tasks
- `revert`: Reverts a previous commit

For major version bumps, mark breaking changes with:
- `type(scope)!: short description`
- or include `BREAKING CHANGE: <description>` in the commit body/footer

Examples:

- `feat(api): support token rotation endpoint`
- `fix(cache): normalize transient cache keys`
- `chore: update CI action pins`
- `feat(api)!: replace token format with JWT`

## Coding Standards

We follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

- Use tabs for indentation
- Use long array syntax: `array()` not `[]`
- Add PHPDoc blocks for all functions
- Sanitize and escape all user input
- Use strict comparisons (`===`, `!==`)

## Testing

Before submitting a PR:

1. Test with WordPress 5.0+
2. Test with PHP 7.4+
3. Test with PMPro 3.0+
4. Test all validation rules
5. Test group creation
6. Test caching functionality

## Documentation

If your contribution adds or changes functionality:

1. Update relevant documentation on the [Wiki](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki)
2. Add code examples if applicable
3. Update [Filters Reference](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Filters-Reference) if adding new filters
4. Update README.md if needed

## Questions?

Feel free to ask questions in:
- [GitHub Discussions](https://github.com/raphaelsuzuki/pmpro-magic-levels/discussions)
- [Issues](https://github.com/raphaelsuzuki/pmpro-magic-levels/issues)

Thank you for contributing! 🎉
