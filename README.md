# VisibleBorder

Visible world border with configurable size, center, and optional kill-on-cross.

<img width="2340" height="1080" alt="Screenshot_20260326_171130_Minecraft" src="https://github.com/user-attachments/assets/b6014adc-f7f2-4f7e-82ec-c983e8652187" />

---

## Quick Start
1) Drop the plugin into `plugins/`.
2) Start the server once (generates `config.yml` + `messages.yml`).
3) In-game (requires `visibleborder.command`):
   ```
   /vb create test
   /vb set test size 10
   /vb set test center     # uses your position
   /vb set test solid true # block in/out
   ```
4) Borders persist in `plugin_data/VisibleBorder/borders.yml`.

---

## Commands
All require `visibleborder.command`. In-game help: `/vb`.

- `/vb create <id>` - create a border in the current world (default size 5, center = you).
- `/vb remove <id>` - delete one border.
- `/vb clear` - delete all borders in this world.
- `/vb list` - list borders in this world.
- `/vb info <id>` - show size/center/solid.
- `/vb set <id> size <value>` - set radius (blocks).
- `/vb set <id> center` - center at your position (snaps to .5/.5).
- `/vb set <id> center <x> <z>` - set center coords.
- `/vb set <id> solid <true/false>` – toggle collision blocking (if true: crossing = instant death).

Bypass permission: `visibleborder.bypass` (ignores collision/knockback).

---

## Behaviour
- Solid=true: players cannot exit or enter; attempts result in instant death.
- Solid=false: border is visual only (no enforcement).
- Sync task pulls stationary players back into compliance each tick.

---

## Configuration
`resources/config.yml`:
```yaml
sync-interval-ticks: 40
```
Sync task also enforces collision/knockback for stationary players.

---

## Messages
`resources/messages.yml` holds all player-facing strings (help, info, feedback).

---

## API (VisibleBorderAPI)
```php
use NhanAZ\VisibleBorder\api\VisibleBorderAPI;
use pocketmine\math\Vector3;

$api = VisibleBorderAPI::get();
$api->createBorder("arena", $world, 15.0, new Vector3(0, 64, 0));
$api->setBorderSize("arena", $world, 20.0);
$api->setBorderCenter("arena", $world, new Vector3(10, 64, 10));
$api->setBorderSolid("arena", $world, true);
```
Methods:
- `createBorder(id, world, size, center): Border`
- `removeBorder(id, world): bool`
- `getBorder(id, world): ?Border`
- `getBordersInWorld(world): Border[]`
- `setBorderSize(id, world, size)`
- `setBorderCenter(id, world, Vector3 $center)`
- `setBorderSolid(id, world, bool $solid)`
- `isInsideBorder(Player $player, string $id): bool`

---

## Troubleshooting
- Border invisible: relog to reload the resource pack; ensure plugin registered (see console).
- Knockback not working: ensure `solid` is true and you don’t have `visibleborder.bypass`.
- Pack cache issues: delete `plugin_data/VisibleBorder/VisibleBorder.mcpack` and restart.
