# Changelog

All notable changes to the `WireSettings` module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-10-01

### Added

- German translation file (`languages/de.csv`) covering all module messages, admin editor labels, shortcuts, and configuration fields.

### Fixed

- Fixed admin menu navigation label not updating when changing "Admin Menu Title" in module configuration.
- Fixed core "Settings" (ProcessPageEditSettings) tab removal by intercepting `ProcessPageEdit::buildFormSettings` and cleaning tab wrappers from the editor form.

## [1.0.0] - 2026-10-01

### Added

- Initial release of **WireSettings**.
- Automatic top-level `/admin/settings/` navigation menu link.
- Hidden data page encapsulation under the admin hierarchy (`/admin/settings/data/`).
- Global `$settings` API variable exposed to all frontend templates and `$wire->settings`.
- Automatic detection and preservation of existing `settings` templates.
- Automatic migration of existing frontend `/settings/` pages into hidden admin storage.
- Editor tab cleanup (hiding destructive _Settings/Delete/Children_ tabs).
- Superuser quick shortcut to **Setup > Templates > settings**.
- Dedicated `wire-settings` permission for granular role-based access control.
- Full multi-language field support and native UIkit 3 styling.
