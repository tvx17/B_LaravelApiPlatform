# Repository Guidelines

## Project Structure & Module Organization

This directory is currently an empty project scaffold. No source code, tests, assets, package manifests, or Git metadata are present. Update this guide when the implementation and toolchain are established.

When adding the initial implementation, group application code in `src/`, automated tests in `tests/`, and static resources in `assets/` if the chosen framework supports that layout. Keep configuration at the project root and document any framework-specific structure.

## Build, Test, and Development Commands

No build, test, or development commands are configured yet. Do not assume that `npm test`, `npm run build`, or another tool-specific command is available.

When introducing a toolchain, record its prerequisites and exact installation, local development, build, and test commands in `README.md`. Prefer reproducible commands using the project's manifest and committed dependency lockfile.

## Coding Style & Naming Conventions

Follow the selected language's standard conventions and use consistent indentation within each file. Prefer descriptive names that explain a module's responsibility. Avoid unrelated formatting changes in functional patches.

Add formatter and linter configuration alongside the first source files, and document how contributors run them. No formatting or linting tools are currently configured.

## Testing Guidelines

No testing framework or coverage threshold is established. Introduce tests for meaningful behavior as functionality is added, including failure cases and boundary conditions. Use descriptive test names and the selected framework's standard file naming pattern. Document the test command before requiring it in reviews.

## Commit & Pull Request Guidelines

No Git history is available to establish existing commit conventions. Use concise, imperative commit subjects, such as `Add simulation entry point`, and keep changes focused.

Pull requests should explain the purpose, summarize the changes, and describe validation performed. Link relevant issues when available. Include screenshots for visible interface changes and disclose any missing checks or configuration requirements.

## Security & Configuration

Keep credentials and machine-specific settings out of version control. Provide placeholder configuration examples and ignore generated outputs and local environment files when introducing the toolchain.
