# Repository Guidelines

## Project Structure & Module Organization

This directory currently contains contributor documentation but no Git metadata, source code, tests, or build configuration. Establish the project structure before adding implementation files, and update this guide when tooling is introduced.

For a Chrome extension boilerplate, a suggested layout is `src/` for extension code, `assets/` for icons and static resources, and `tests/` for automated tests. Keep the extension manifest with the extension source. Document the actual layout once it exists.

## Build, Test, and Development Commands

No build, test, or development commands are configured yet. Do not assume that `npm install`, `npm test`, or `npm run build` is available.

When adding tooling, define repeatable commands in the project configuration and document them here. Include dependency installation, local development, linting, testing, and production packaging instructions. Explain where generated output is written and how to load the extension locally.

## Coding Style & Naming Conventions

No formatter or linter is configured. Until a project standard is established, use two-space indentation for JavaScript, JSON, and Markdown examples. Choose descriptive filenames and identifiers, and keep naming consistent within each module.

Configure formatting and linting alongside the first source files. Keep generated bundles and downloaded dependencies out of source control.

## Testing Guidelines

No testing framework or coverage threshold exists yet. Introduce tests with the first executable functionality. Use descriptive test names that state the behavior being verified; follow the selected framework’s file naming convention.

Document the test command and any browser setup requirements. For extension changes, verify installation and affected browser interactions manually in addition to automated checks.

## Commit & Pull Request Guidelines

There is no Git history from which to infer commit conventions. Use concise, imperative commit subjects, such as `Add extension manifest`, and keep commits focused.

Pull requests should describe the change, its purpose, and validation performed. Link related issues when available and include screenshots for visible interface changes. Identify any new dependencies, permissions, or configuration requirements.

## Security & Configuration

Never commit secrets or private configuration. If building a Chrome extension, request only the permissions required by implemented features and explain new permissions in the pull request.

## Change Documentation

For every task that changes repository files, add a dated entry to `CHANGES.md` before finishing. Write entries in German, group related changes, and briefly describe their purpose and result. Include documentation and configuration changes; avoid implementation-level detail and routine inspection logs.

Always document relevant executable steps visibly: put CLI commands in fenced code blocks and browser URLs in clickable links. State the working directory, prerequisites, and execution order when needed. Distinguish commands already executed (with their result) from steps the user still needs to run. Never include secrets; use clearly labeled placeholders. If no commands or browser steps are required, say so explicitly. Preserve previous entries and put new entries first.
