# Repository Guidelines

## Project Structure & Module Organization

This repository currently contains no application code, build configuration, or tests. Keep this guide aligned with the project as those components are introduced.

When establishing the application, group source code by feature and keep shared utilities separate. Use clearly named directories such as `src/`, `tests/`, and `public/` where appropriate for the chosen framework. Keep configuration files at the repository root and document any additional directory conventions.

## Build, Test, and Development Commands

No development, build, or test commands are configured yet. When adding tooling, provide reproducible commands for local development, production builds, linting, and testing in the project README.

If the project adopts npm, define scripts such as `npm run dev`, `npm run build`, and `npm test` in `package.json` before documenting them as supported commands. Commit the relevant dependency lockfile.

## Coding Style & Naming Conventions

Follow the conventions of the selected language and framework. Configure a formatter and linter early so contributors share enforceable rules for indentation, imports, and whitespace.

Use descriptive names, consistent filename casing, and small modules with focused responsibilities. Avoid unrelated formatting changes in functional pull requests.

## Testing Guidelines

No testing framework or coverage threshold is established. Add tests alongside new behavior using the selected framework's naming conventions, such as `*.test.ts` if applicable. Cover important behavior, error paths, and regressions. Document test setup and execution when introducing the test suite.

## Commit & Pull Request Guidelines

No Git history is available to establish an existing commit convention. Use concise, imperative commit subjects, such as `Add navigation layout`.

Pull requests should describe the change, its purpose, and validation performed. Link relevant issues and include screenshots for visible interface changes. Identify any configuration changes or setup steps reviewers need.

## Security & Configuration

Keep secrets and local environment files out of version control. Provide example configuration with placeholder values and document required settings when configuration is introduced.
