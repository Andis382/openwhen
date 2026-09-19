# OpenWhen — Design System (Master)

Source of truth for every screen. Built with the **UI/UX Pro Max** skill and
reconciled by hand; each row records the search it came from, including where a
verified result was set aside and why.

```bash
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "logistics delivery fleet field operations" --domain color
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "dark operations dashboard technical precision" --domain typography
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "touch target size spacing" --domain ux
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "blade layout accessibility form validation" --stack laravel
```

---

## 1. Product

| | |
|---|---|
| **Type** | A one-tap field tool, plus a small dispatcher surface. |
| **Users** | A driver in a van at 05:30 with the interior light off. A dispatcher at a desk with a spreadsheet open. |
| **The job** | One tap per stop; a route in time order tomorrow. |
| **Stack** | Laravel 12 + Blade, hand-written CSS, no Node, no build step (`--stack laravel`). |

**Dark is the default and light is the exception.** Every other product in this
family defaults to light; this one does not, and the reason is not taste. The
screen that matters is held one-handed before dawn in a cab, and a white screen
at that hour is a screen nobody looks at twice.

## 2. Colour

`--domain color` → the **Logistics/Delivery** palette, "Tracking blue +
delivery orange". The orange is taken as the primary and the blue dropped: the
other product built alongside this one is already a blue field tool, and two
tools by the same hand should not look like the same tool.

| Role | Dark (default) | Light (dispatcher) |
|---|---|---|
| `--brand` | `#ff8a3d` | `#c2560f` |
| `--canvas` / `--surface` | `#0a0d12` / `#141a22` | `#eef1f5` / `#ffffff` |
| `--header` | `#05080c` → `#0d131b` | same (the header stays dark in both) |
| `--ink` / `--ink-2` / `--ink-3` | `#edf2f7` / `#a7b5c4` / `#7e8fa1` | `#0d141c` / `#4a5967` / `#66798a` |
| `--ok` delivered | `#43d17f` on `#0e2a1b` | `#0c7a45` on `#e3f6ec` |
| `--bad` shutter down | `#ff6b5e` on `#2c1412` | `#c0392b` on `#fceae8` |
| `--warn` owner away | `#f2b544` on `#2b2110` | `#8f6008` on `#fdf2dc` |
| `--calm` refused | `#93a5b8` on `#1a222c` | `#536170` on `#eaeef3` |

**Four outcomes, four colours, and the distinction they encode is the product.**
A refusal and a delivery are both evidence the shutter was up; only red means
shut. Amber — owner away — is the awkward one: the shop was open and the stop
was still wasted, and collapsing those two facts is how every field-sales app
loses the only signal worth having.

The heat map uses the brand orange for "usually open" and a dimmed red for
"usually shut", with flat surface for "not enough visits". Every cell also
carries a full sentence for a screen reader, because a colour-only grid says
nothing to anybody who cannot see it.

## 3. Typography

`--domain typography` returned **Dashboard Data** (Fira Code + Fira Sans) as
the literal fit for "dashboards, analytics, admin panels". Set aside: this is
not an admin panel, it is four buttons in a moving vehicle. Taken instead from
the same search: **Space Grotesk** for display — wide, slightly technical
letterforms that hold up at arm's length on a dashboard-mounted phone — with
**Inter** for everything typed into and **JetBrains Mono** for times and codes.
Recorded here because it is a judgement rather than a lookup.

Scale: 11 · 13 · 14 · 16 · 18 · 22 · 28 · 36 · 48. Body never below 16px.

## 4. Shape, spacing, depth

4px rhythm. Radius 8 / 12 / 16 / 22.

**`--tap: 56px`, not 48.** The usual floor is a platform minimum for a hand at
rest. This is gloves, a moving van, and one tap that must not be the wrong one,
because a mis-tap does not merely annoy somebody — it writes a false
observation into a shop's history that nothing downstream can detect.

The four outcome buttons are a two-by-two grid filling the card, each one the
width of half a phone. Nothing else on that screen competes with them.

## 5. Icons — Phosphor, regular weight

76 glyphs copied from `@phosphor-icons/core` (MIT) into `resources/icons.php`
as raw path data: no package, no font, no build step. Regenerate with
`scripts/build-icons.py`. Emoji are banned and the ban is tested.

The four outcomes each get a distinct glyph — a tick, a door, a person, a
prohibition — so the buttons are separable in greyscale and at a glance.

## 6. The offline badge

A small amber pill in the header that appears only when there is no signal or
something is still queued. It is in the header rather than beside the buttons on
purpose: a driver must be able to answer "are my taps being kept?" without
asking anybody and without tapping anything.

## 7. Motion

Colour and shadow at 110–180ms, a one-pixel lift on press.
`prefers-reduced-motion: reduce` removes all of it, and that is tested.

## 8. Rules this interface keeps

- Skip link; focus ring never removed; `aria-current` on the active tab.
- Every field has a visible label; the error sits under its own field, tied with
  `aria-describedby` and `aria-invalid`; a failed form gets a focusable summary
  at the top linking to each bad field.
- Colour is the third signal — every tag carries an icon and a word, and every
  heat-map cell carries a sentence.
- Mobile first, `min-height: 100dvh`, no horizontal scroll at 375px, safe-area
  padding under the tab bar. The heat map is the one thing allowed to scroll
  sideways, inside its own container.
- Four bottom destinations, each with an icon *and* a word.

## 9. Anti-patterns

- Emoji as icons *(tested against)*
- A raw hex in a screen *(tested against)*
- A status tag without an icon *(tested against)*
- A heat map that means something only in colour
- Stating a rule the arithmetic has not earned
  *(the thresholds are constants, and each is pinned in a test)*
- Small text — nothing below 11px, and 11px only for uppercase labels
