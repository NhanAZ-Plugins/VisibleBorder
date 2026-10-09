# Changelog

## 1.0.1 - 2026-10-10

- Target Axolotl-PM 5.49.1 and bundle libRegRsp 1.0.5 with DevTools 1.0.1.
- Replace dynamic class construction in entity registration so the plugin can be shaded into a PHAR.
- Validate stored border values and command messages before using them.
- Run PHPStan at level max and verify the PHAR and bundled legal texts in CI.
- Preserve the existing commands, permissions, resource pack files and border data path.

Stop the server before installing version 1.0.1. Keep the previous PHAR and a copy of `plugin_data/VisibleBorder/` for rollback. Client rendering and crossing behavior still require in-game testing.
