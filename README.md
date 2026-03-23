# VisibleBorder (PM5)

A PocketMine-MP 5 plugin that shows **client-side world borders** with configurable collision, damage, knockback, lifetimes, and smooth shrinking/expanding. Multiple named borders can coexist per world. All player-facing text is configurable and English-only.

---

## Quick Start
1) Drop the plugin into `plugins/`.
2) Start the server once to generate `resources/config.yml` and `resources/messages.yml`.
3) In-game (with permission `visibleborder.command`):
   ```txt
   /vb create test
   /vb set test size 10
   /vb set test center    # uses your position
   /vb set test solid true
   ```
4) Borders are persistent; data is stored in `plugin_data/VisibleBorder/borders.yml`.

---

## Commands
All commands start with `/vb` and require `visibleborder.command`.

- `/vb create <id>` - create a border in the current world (default size = `min_size` from config).
- `/vb remove <id>` - delete one border.
- `/vb clear` - delete all borders in the current world.
- `/vb list` - list borders in the current world.
- `/vb info <id>` - show full settings.

Setters:
- `/vb set <id> size <value>`
- `/vb set <id> size <target> <seconds>` - animate shrink/expand to target over time.
- `/vb set <id> minsize <value>`
- `/vb set <id> lifetime <seconds>` - auto-remove after duration (0 to disable).
- `/vb set <id> center` - use player position.
- `/vb set <id> center <x> <z>`
- `/vb set <id> solid <true/false>` - toggle collision block.
- `/vb set <id> speed <blocks/s>` - default animation speed if duration omitted.

Damage:
- `/vb set <id> damage amount <value>`
- `/vb set <id> damage distance <value>`
- `/vb set <id> damage delay <seconds>`

Knockback:
- `/vb set <id> knockback power <value>`
- `/vb set <id> knockback distance <value>`
- `/vb set <id> knockback delay <seconds>`

On-zero action (size reaches 0):
- `/vb set <id> onzero <kill|freeze|damage <amount>>`

Bypass permission: `visibleborder.bypass` (ignores collision/damage/knockback).

---

## Configuration
File: `resources/config.yml`
```yaml
defaults:
  min_size: 1.0
  solid: true
  damage:
    amount: 1.0
    distance: 1.0
    delay: 1.0
  knockback:
    power: 0.6
    distance: 0.5
    delay: 0.5
  on_zero: "kill"
  speed: 0.0          # default shrink/expand speed (blocks/s) if duration is omitted
sync-interval-ticks: 40
```
- `sync-interval-ticks`: how often players are resynced and outside players are corrected.
- Per-border values start from these defaults and can be overridden via commands.

---

## Messages
File: `resources/messages.yml` - all player-facing strings. Example keys:
```yaml
usage: "/vb create/remove/set/info/list/clear"
border-created: "Border {id} created in this world."
border-info: "Border {id}: size {size}, min {min}, center ({x},{z}), solid {solid}, damage {dmg}@{ddist}/{ddelay}s, kb {kbp}@{kbdist}/{kbdelay}s, onZero {onzero}"
...
```
Placeholders are wrapped in `{}` and substituted in code.

---

## Storage
- Borders persist in `plugin_data/VisibleBorder/borders.yml` (per-world, keyed by ID).
- Lifetimes store an absolute expiry timestamp and are restored on restart (expired borders are removed at load).

---

## Behaviour & Edge Cases
- **Min size**: All size changes clamp to `min_size`.
- **Shrink/expand**: If duration is omitted and a `speed` is set, duration is derived from distance / speed.
- **Collision**: When `solid=true`, movement is clamped to the radius; stationary players outside are pulled back during sync ticks.
- **Damage**: Applies outside the border + `damage.distance`, with per-player cooldown `damage.delay`.
- **Knockback**: Direction is `(player - center).normalize()` (horizontal), triggered outside radius + `knockback.distance`, with per-player cooldown `knockback.delay`.
- **onZero**: When size hits 0, actions: `kill`, `freeze`, or `damage <amount>` then the border is removed. Marked with `// FIXED:` comments in code.

---

## API Usage (PHP)
Namespace: `NhanAZ\VisibleBorder\api\VisibleBorderAPI`

```php
use NhanAZ\VisibleBorder\api\VisibleBorderAPI;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

$api = VisibleBorderAPI::get();

// Create
$border = $api->createBorder("pvp", $player->getWorld(), 20.0, $player->getPosition());

// Animate shrink to 5 over 30s
$api->shrinkBorder("pvp", $player->getWorld(), 5.0, 30.0);

// Lifetime 10 minutes
$api->setBorderLifetime("pvp", $player->getWorld(), 600);

// Query
$inside = $api->isInsideBorder($player, "pvp");

// Remove
$api->removeBorder("pvp", $player->getWorld());
```
Each method throws `RuntimeException` with a descriptive message if the border is missing.

Available API methods:
- `createBorder(string $id, World $world, float $size, Vector3 $center): Border`
- `removeBorder(string $id, World $world): bool`
- `getBorder(string $id, World $world): ?Border`
- `getBordersInWorld(World $world): Border[]`
- `shrinkBorder(string $id, World $world, float $targetSize, float $seconds): void`
- `setBorderLifetime(string $id, World $world, float $seconds): void`
- `isInsideBorder(Player $player, string $id): bool`

`Border` model getters/setters: `getSize()`, `setSize()`, `getMinSize()`, `setMinSize()`, `getSpeed()`, `setSpeed()`, `getCenter()`, `setCenter()`, `isSolid()`, `setSolid()`, damage/knockback accessors, `getOnZeroAction()`, `setOnZeroAction()`, `getExpiresAt()/setExpiresAt()`.

---

## Permissions
- `visibleborder.command` - required for all `/vb` commands (default: op).
- `visibleborder.bypass` - ignore collision/damage/knockback checks.

---

## Requirements
- PocketMine-MP API 5.0+.
- Resource pack is auto-built and force-registered by the plugin on enable (no manual pack work needed).

---

## Troubleshooting
- Border not visible: ensure resource packs are forced (handled by plugin), relog if client cached old pack.
- Hitbox showing: the border entity uses minimal bounding box; ensure no client-side hitbox overlay mods are forcing display.
- Damage/knockback not firing: check `visibleborder.bypass` permission and distances (`damage.distance`, `knockback.distance`).
- Shrink stops early: verify `min_size` is not higher than target.

---

## Uninstall / Reset
- Remove the plugin and delete `plugin_data/VisibleBorder/` to clear saved borders and generated packs.

