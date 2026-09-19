#!/usr/bin/env python3
"""Copy the Phosphor glyphs this interface uses into a PHP map.

Phosphor Icons is MIT licensed, (c) 2023 Phosphor Icons. Only the handful this
app actually references is copied, so OpenWhen needs no icon package, no icon
font and no build step: the path data ships as plain PHP.

    npm i @phosphor-icons/core@2.1.1        # in any scratch directory
    python3 scripts/build-icons.py resources/icons.php
"""
import os
import re
import sys

SRC = os.environ.get("PHOSPHOR", "/tmp/phos/node_modules/@phosphor-icons/core/assets/regular")

WANTED = {
    # navigation
    "home": "house", "route": "path", "map": "map-trifold", "chart": "chart-line-up",
    "settings": "gear-six", "shops": "storefront", "users": "users-three",
    "logout": "sign-out", "chevron": "caret-right", "back": "arrow-left",
    "close": "x", "dots": "dots-three", "external": "arrow-square-out",
    "plus": "plus", "calendar": "calendar-dots",

    # the four outcomes, which are the whole product
    "delivered": "check-circle", "closed": "door", "absent": "user",
    "refused": "prohibit",

    # field
    "van": "van", "truck": "truck", "navigate": "navigation-arrow",
    "pin": "map-pin", "crosshair": "crosshair", "signpost": "signpost",
    "barcode": "barcode", "door-open": "door-open", "steering": "steering-wheel",
    "offline": "wifi-slash", "sync": "cloud-arrow-up", "countdown": "clock-countdown",

    # status and actions
    "check": "check", "check-circle": "check-circle", "warning": "warning",
    "warning-circle": "warning-circle", "clock": "clock", "hourglass": "hourglass-medium",
    "prohibit": "prohibit", "phone": "phone", "chat": "chat-circle-text",
    "copy": "copy", "printer": "printer", "search": "magnifying-glass",
    "user": "user", "trash": "trash", "edit": "pencil-simple",
    "note": "note-pencil", "info": "info", "file": "file-text",
    "download": "download-simple", "upload": "upload-simple", "retry": "arrow-clockwise",
    "list": "list-checks", "lock": "lock-simple", "key": "key",
    "link": "link-simple", "eye": "eye", "star": "star", "minus": "minus",
    "question": "question", "archive": "archive", "clipboard": "clipboard-text",
    "trend-up": "trend-up", "trend-down": "trend-down", "percent": "percent",
    "sun": "sun", "moon": "moon", "sunrise": "sun-horizon", "timer": "timer",
    "sort": "arrows-down-up", "funnel": "funnel", "sliders": "sliders-horizontal",
    "gauge": "gauge", "target": "target", "flag": "flag", "lightning": "lightning",
}

INNER = re.compile(r"<svg[^>]*>(.*)</svg>", re.S)

HEADER = """<?php

/*
 * Phosphor Icons, regular weight, MIT licensed, (c) 2023 Phosphor Icons.
 * https://github.com/phosphor-icons/core
 *
 * Only the glyphs this interface uses, as raw path data on a 256 unit grid, so
 * that the app needs no icon package, no icon font and no build step.
 * Regenerate with scripts/build-icons.py after adding a name.
 *
 * Never emoji. They are drawn by whatever font the device carries, render at a
 * size nobody chose, differ between Android and iOS, and cannot take a colour
 * from a design token -- so the glyph that has to mean "delivered" or "closed"
 * could not be relied on to look like anything in particular.
 */

return [
"""


def main():
    out, missing = [], []

    for key in sorted(WANTED):
        path = os.path.join(SRC, WANTED[key] + ".svg")
        if not os.path.exists(path):
            missing.append((key, WANTED[key]))
            continue
        match = INNER.search(open(path, encoding="utf-8").read())
        if not match:
            missing.append((key, WANTED[key]))
            continue
        body = match.group(1).strip().replace("\n", "")
        assert "'" not in body, key
        out.append("    '%s' => '%s'," % (key, body))

    if missing:
        print("MISSING:", missing, file=sys.stderr)

    dest = sys.argv[1] if len(sys.argv) > 1 else "resources/icons.php"
    with open(dest, "w", encoding="utf-8") as fh:
        fh.write(HEADER + "\n".join(out) + "\n];\n")
    print("wrote %d icons to %s" % (len(out), dest))


if __name__ == "__main__":
    main()
