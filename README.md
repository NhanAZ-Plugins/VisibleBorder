# VisibleBorder

VisibleBorder adds a configurable, visible world border to Axolotl-PM worlds. Version 1.0.1 declares plugin API 5.0.0 and requires PHP 8.1 or later. Broader Axolotl-PM 5.x compatibility remains unverified.

The plugin places a custom entity around each border and bundles a matching Bedrock resource pack. Its optional solid mode kills a player who crosses a border. Border definitions are saved in `plugin_data/VisibleBorder/borders.yml`.

![A VisibleBorder example in Minecraft](https://github.com/user-attachments/assets/b6014adc-f7f2-4f7e-82ec-c983e8652187)

## Requirements

- Axolotl-PM with plugin API 5.0.0. The exact tested server revision is pinned in the [build workflow](.github/workflows/build.yml).
- PHP 8.1 or later with the extensions required by Axolotl-PM, including `zip`.
- A Bedrock client that accepts the bundled resource pack to see the border.
- On Axolotl-PM API builds without a runtime setter for resource requirements, set `force_resources: true` in `resource_packs/resource_packs.yml` before starting the server.

The published PHAR bundles its library dependency, so installing libRegRsp separately is unnecessary. Current source builds use the version declared in [devtools.yml](devtools.yml).

The existing release PHAR has only been runtime-tested on its recorded Axolotl-PM release. Current source builds use libRegRsp 1.0.6; server behavior at the declared API floor has not yet been runtime-tested for this plugin.

## Installation

1. Download `VisibleBorder.phar` from the [v1.0.1 release](https://github.com/NhanAZ-Plugins/VisibleBorder/releases/tag/v1.0.1).
2. Place `VisibleBorder.phar` in the Axolotl-PM `plugins` directory.
3. Start the server, join with a Bedrock client and accept the resource pack.
4. Run `/vb help` in game. The command requires `visibleborder.command`.

The plugin creates `config.yml`, `messages.yml` and `borders.yml` in its data directory. Existing border data uses the same path and structure as version 1.0.0.

## Commands and permissions

| Command | Effect |
|---|---|
| `/vb create <id>` | Create a border in the current world. |
| `/vb remove <id>` | Remove one border. |
| `/vb clear` | Remove all borders in the current world. |
| `/vb list` | List borders in the current world. |
| `/vb info <id>` | Show size, center and solid mode. |
| `/vb set <id> size <value>` | Set the border radius in blocks. |
| `/vb set <id> center` | Move the center to the player's current position. |
| `/vb set <id> center <x> <z>` | Set explicit center coordinates. |
| `/vb set <id> solid <true|false>` | Enable or disable instant death on crossing. |

`visibleborder.command` controls command access and defaults to operators. `visibleborder.bypass` exempts a player from crossing enforcement and also defaults to operators. Console senders cannot run the in-game border commands.

## Configuration and behavior

`sync-interval-ticks` in `config.yml` defaults to `40`. A non-positive or invalid value falls back to `40`. Restart the server after editing configuration or messages. Set `solid` to `false` for a visual border with no crossing penalty.

The resource pack is generated from `resources/VisibleBorder Pack/` and registered when the plugin starts. It is removed from the server resource stack when the plugin stops. The previous generated pack file is preserved if a new pack cannot be compiled. A resource pack failure disables the plugin so it does not run without its visual asset.

## API

`NhanAZ\VisibleBorder\api\VisibleBorderAPI::get()` exposes border management to other plugins. The plugin must be enabled before this API is used.

```php
use NhanAZ\VisibleBorder\api\VisibleBorderAPI;
use pocketmine\math\Vector3;

$api = VisibleBorderAPI::get();
$api->createBorder("arena", $world, 15.0, new Vector3(0, 64, 0));
$api->setBorderSize("arena", $world, 20.0);
$api->setBorderCenter("arena", $world, new Vector3(10, 64, 10));
$api->setBorderSolid("arena", $world, true);
```

See [VisibleBorderAPI.php](src/NhanAZ/VisibleBorder/api/VisibleBorderAPI.php) for methods and return types.

## Building with DevTools

This repository declares libRegRsp `^1.0.6` in `devtools.yml`. CI checks out libRegRsp and Axolotl-PM source at pinned commits, analyzes the plugin at PHPStan level max, builds a standalone PHAR with [DevTools](https://github.com/NhanAZ/DevTools), verifies dependency licenses and uploads the result. The workflow pins the builder revision.

For a local build, place libRegRsp source at `virions/libRegRsp` and run:

```sh
php -d phar.readonly=0 path/to/DevTools/bin/devtools.php build --project=. --virions=virions --out=build
```

A successful build proves packaging. A clean server boot verifies plugin loading and resource pack lifecycle. Actual rendering, entity positioning and crossing behavior still require a Bedrock client test.

## Upgrade and rollback

Version 1.0.1 keeps the existing command names, permissions and border data path. Stop the server before replacing the PHAR. Keep a copy of the previous PHAR and data directory, then install the new build and reconnect players to refresh the pack. Restore the previous PHAR and saved data if an issue appears.

## License and support

VisibleBorder source and its bundled resource pack are distributed under the [MIT License](LICENSE). libRegRsp is separately licensed under LGPL-3.0-or-later, and its license is included in the PHAR. The repository history identifies NhanAZ as the human source contributor. For support, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c) or open a GitHub issue.
