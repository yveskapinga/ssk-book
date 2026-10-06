"""Build Play Store 7\" / 10\" tablet screenshots from phone captures.

Uses the real tablet chrome (left nav rail) from the Expo app layout.
Output is 24-bit PNG (no alpha), ratios accepted by Play (between 9:16 and 16:9).
"""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent

INK = (10, 39, 68)
PAPER = (245, 248, 253)
PANEL = (255, 255, 255)
LINE = (212, 226, 244)
SIDEBAR = (11, 74, 162)
SIDEBAR_TEXT = (255, 255, 255)
SIDEBAR_MUTED = (185, 212, 255)
GOLD = (255, 209, 0)
RAIL_ICON = (231, 240, 255)

# Crop Expo Go chrome: status bar + bottom tab bar.
CROP_TOP = 132
CROP_BOTTOM = 2140

PHONE_SHOTS = [
    ("phone-01-accueil.png", "Plus", "menu"),
    ("phone-02-lecture.png", "Lecture", "book"),
    ("phone-03-quiz.png", "Quiz", "quiz"),
    ("phone-04-questions.png", "Q&R", "chat"),
    ("phone-05-plus.png", "Plus", "menu"),
    ("phone-06-suivi.png", "Suivi", "menu"),
    ("phone-07-profil.png", "Profil", "menu"),
]

SIZES = {
    "7": (1200, 1920),   # shortest 1200 >= 600
    "10": (1600, 2560),  # shortest 1600 >= 1080
}

TABS = [
    ("Accueil", "home"),
    ("Lecture", "book"),
    ("Quiz", "quiz"),
    ("Q&R", "chat"),
    ("Plus", "menu"),
]


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    names = (
        "C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf",
        "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
    )
    for name in names:
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default()


def draw_icon(draw: ImageDraw.ImageDraw, kind: str, cx: int, cy: int, color: tuple[int, int, int], s: int) -> None:
    if kind == "home":
        draw.polygon([(cx, cy - s), (cx - s, cy), (cx + s, cy)], outline=color, width=2)
        draw.rectangle([cx - s // 2, cy, cx + s // 2, cy + s], outline=color, width=2)
    elif kind == "book":
        draw.rectangle([cx - s, cy - s, cx + s, cy + s], outline=color, width=2)
        draw.line([(cx, cy - s), (cx, cy + s)], fill=color, width=2)
    elif kind == "quiz":
        draw.ellipse([cx - s, cy - s, cx + s, cy + s], outline=color, width=2)
        draw.line([(cx, cy - s // 2), (cx, cy + 1)], fill=color, width=2)
        draw.ellipse([cx - 2, cy + s // 2, cx + 2, cy + s // 2 + 4], fill=color)
    elif kind == "chat":
        draw.rounded_rectangle([cx - s, cy - s + 2, cx + s, cy + s // 2], radius=6, outline=color, width=2)
        draw.polygon([(cx - 4, cy + s // 2), (cx - s, cy + s), (cx + 2, cy + s // 2)], fill=color)
    else:
        for i in range(3):
            y = cy - s + i * s
            draw.line([(cx - s, y), (cx + s, y)], fill=color, width=3)


def compose(src: Path, active: str, active_icon: str, size: tuple[int, int]) -> Image.Image:
    W, H = size
    rail = 220 if W < 1400 else 280
    canvas = Image.new("RGB", (W, H), PAPER)
    draw = ImageDraw.Draw(canvas)

    draw.rectangle([0, 0, rail, H], fill=SIDEBAR)

    title_f = font(28 if W < 1400 else 36, bold=True)
    draw.text((20, 28), "SSK ", font=title_f, fill=SIDEBAR_TEXT)
    tw = draw.textlength("SSK ", font=title_f)
    draw.text((20 + tw, 28), "Book", font=title_f, fill=GOLD)

    item_h = 52 if W < 1400 else 64
    y = 100
    item_f = font(16 if W < 1400 else 20, bold=True)
    for label, kind in TABS:
        is_active = label == active or (active in {"Suivi", "Profil"} and label == "Plus")
        box = [10, y, rail - 10, y + item_h - 6]
        if is_active:
            draw.rounded_rectangle(box, radius=12, fill=GOLD)
        cx, cy = 32, y + (item_h - 6) // 2
        color = INK if is_active else RAIL_ICON
        draw_icon(draw, kind, cx, cy, color, 9)
        draw.text((50, y + 12), label, font=item_f, fill=color)
        y += item_h

    draw.line([(16, H - 88), (rail - 16, H - 88)], fill=(40, 96, 180), width=1)
    name_f = font(15 if W < 1400 else 18, bold=True)
    muted_f = font(12 if W < 1400 else 14)
    draw.text((16, H - 72), "Yves Kapinga", font=name_f, fill=SIDEBAR_TEXT)
    draw.text((16, H - 48), "Lecteur", font=muted_f, fill=SIDEBAR_MUTED)

    phone = Image.open(src).convert("RGB")
    content = phone.crop((0, CROP_TOP, phone.width, CROP_BOTTOM))
    area_w, area_h = W - rail, H
    fitted = content.copy()
    fitted.thumbnail((area_w, area_h), Image.Resampling.LANCZOS)
    ox = rail + (area_w - fitted.width) // 2
    oy = (area_h - fitted.height) // 2
    canvas.paste(fitted, (ox, oy))
    return canvas


def main() -> None:
    for src_name, active, icon in PHONE_SHOTS:
        src = ROOT / src_name
        if not src.exists():
            raise SystemExit(f"missing {src}")
        stem = src.stem.replace("phone-", "")
        for label, size in SIZES.items():
            out = ROOT / f"tablet{label}-{stem}.png"
            img = compose(src, active, icon, size)
            img.save(out, format="PNG", optimize=True)
            print(f"wrote {out.name} {img.size}")


if __name__ == "__main__":
    main()
