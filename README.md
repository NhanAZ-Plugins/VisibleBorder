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

- **Quick help in-game:** `/vb` will print the full command cheat-sheet (from `messages.yml -> help`).

- `/vb create <id>` — create a border in the current world (default size = `min_size` from config).
- `/vb remove <id>` — delete one border.
- `/vb clear` — delete all borders in the current world.
- `/vb list` — list borders in the current world.
- `/vb info <id>` — show full settings.

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

## Practical Scenarios (apply every command)

### 1) Basic protected spawn
```
/vb create spawn
/vb set spawn size 32
/vb set spawn center           # uses your position (snaps to .5/.5)
/vb set spawn solid true
```
Result: solid circular border; movement is clamped at radius 32.

### 2) PvP arena shrink
```
/vb create arena
/vb set arena size 100
/vb set arena center 0 0
/vb set arena solid true
/vb set arena speed 2           # default shrink speed (blocks/s)
/vb set arena size 10           # no seconds given → uses speed to compute duration
/vb set arena onzero kill
```
Result: battle-royale style shrink to radius 10; if zero is reached, players are killed.

### 3) Timed event zone
```
/vb create event
/vb set event size 40
/vb set event lifetime 600      # 10 minutes
```
Result: border auto-removes after 10 minutes.

### 4) Soft warning zone (no solid wall)
```
/vb create warning
/vb set warning size 50
/vb set warning solid false
/vb set warning knockback power 1.2
/vb set warning knockback distance 0.5
/vb set warning knockback delay 1.0
/vb set warning damage amount 2
/vb set warning damage distance 1.5
/vb set warning damage delay 2
```
Result: outside players are pushed back and take 2 damage every 2s when 1.5 blocks beyond radius 50.

### 5) Minimum size & freeze at zero
```
/vb create cage
/vb set cage size 8
/vb set cage minsize 3
/vb set cage onzero freeze
```
Result: size never goes below 3; if it ever hits 0, players are frozen.

### 6) Multi-border cleanup
```
/vb list
/vb clear
/vb create north
/vb create south
/vb set north center 0 -200
/vb set south center 0 200
```
Result: two separate borders; `clear` wipes all in the current world.

### Argument reference (what each parameter means)
- `<id>`: unique per world (string, case-sensitive).
- `size <value>`: radius (float). `size <target> <seconds>` animates; if `<seconds>` omitted and `speed` set, duration = distance / speed.
- `minsize <value>`: lower bound for all size changes.
- `lifetime <seconds>`: auto-remove after duration; 0 disables.
- `center`: use player position (snaps to X/Z .5). `center <x> <z>` sets exact coords (Y stays stored).
- `solid <true/false>`: when true, movement clamped to radius; when false, only damage/knockback apply.
- `speed <blocks/s>`: default shrink speed when no duration is provided.
- `damage amount|distance|delay`: hearts to remove, how far outside it starts, and per-player cooldown.
- `knockback power|distance|delay`: motion strength, start distance, and cooldown.
- `onzero <kill|freeze|damage <amount>>`: action when radius reaches 0.

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
