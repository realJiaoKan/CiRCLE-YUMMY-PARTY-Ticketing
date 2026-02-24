import re
from PIL import Image, ImageDraw, ImageFont
import qrcode

from sign import sign
from mailer import send_ticket_email
from database import create_ticket

from settings import *


def make_qr(data):
    qr = qrcode.QRCode(box_size=QR_BOX_SIZE, border=QR_BORDER)
    qr.add_data(data)
    qr.make(fit=True)
    return qr.make_image(fill_color="black", back_color="white").convert("RGB")


def truncate_long_text(draw, text, font, max_width):
    if draw.textlength(text, font=font) <= max_width:
        return text
    ellipsis = "…"
    if draw.textlength(ellipsis, font=font) > max_width:
        return ""
    lo, hi = 0, len(text)
    while lo < hi:
        mid = (lo + hi) // 2
        candidate = text[:mid] + ellipsis
        if draw.textlength(candidate, font=font) <= max_width:
            lo = mid + 1
        else:
            hi = mid
    return text[: max(0, lo - 1)] + ellipsis


def centered_text_y(draw, y_line_top, line_h, text, font):
    bbox = draw.textbbox((0, 0), text, font=font)
    text_h = bbox[3] - bbox[1]
    y_pixels_top = y_line_top + (line_h - text_h) // 2
    return int(y_pixels_top - bbox[1])


def generate_ticket(name, email, table_no):
    if not re.match(r"^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$", email):
        raise ValueError(f"Invalid email format: {email}")

    table_no_raw = str(table_no).strip()
    if not table_no_raw.isdigit():
        raise ValueError(f"Invalid table number: {table_no}")
    table_no_int = int(table_no_raw)
    if not (0 <= table_no_int <= 99):
        raise ValueError(f"Invalid table number (must be 0-99): {table_no}")
    table_no_text = f"{table_no_int:02d}"

    created = create_ticket(
        name=name,
        email=email,
        signer=lambda ticket_no: sign(
            private_key_path=PRIVATE_KEY_PATH,
            message=ticket_no.encode("utf-8"),
            b64url=True,
        ),
        checked="0",
    )
    ticket_no = created["ticket_no"]
    signature = created["sig_b64"]
    qr_payload = f"{ticket_no},{signature}"
    qr_img = make_qr(qr_payload).resize((QR_SIZE, QR_SIZE), Image.Resampling.NEAREST)

    canvas = Image.open(TEMPLATE_PATH).convert("RGB")
    canvas.paste(qr_img, (QR_X, QR_Y))
    draw = ImageDraw.Draw(canvas)

    # Info
    label_font = ImageFont.truetype(str(INFO_LABEL_FONT_PATH), INFO_LABEL_FONT_SIZE)
    value_font = ImageFont.truetype(str(INFO_VALUE_FONT_PATH), INFO_VALUE_FONT_SIZE)
    no_font = ImageFont.truetype(str(INFO_NO_FONT_PATH), INFO_LABEL_FONT_SIZE)

    # Truncate values if too long
    rows = [
        ("名前：", name, value_font),
        ("番号：", ticket_no, no_font),
        ("卓番：", table_no_text, no_font),
    ]
    label_w = max(int(draw.textlength(label, font=label_font)) for label, _, _ in rows)
    value_x = TICKET_INFO_X + label_w
    max_value_width = (QR_X - 20) - value_x

    rows = [
        (label, truncate_long_text(draw, value, font, max_value_width), font)
        for label, value, font in rows
    ]

    row_heights = []
    for label, value, font in rows:
        label_bbox = draw.textbbox((0, 0), label, font=label_font)
        value_bbox = draw.textbbox((0, 0), value, font=font)
        label_h = label_bbox[3] - label_bbox[1]
        value_h = value_bbox[3] - value_bbox[1]
        row_heights.append(max(label_h, value_h))

    total_h = sum(row_heights) + INFO_LINE_GAP * (len(rows) - 1)
    start_y = TICKET_INFO_Y + (TICKET_INFO_HEIGHT - total_h) // 2
    x = TICKET_INFO_X

    y = start_y
    for (label, value, font), row_h in zip(rows, row_heights):
        draw.text(
            (x, centered_text_y(draw, y, row_h, label, label_font)),
            label,
            fill="black",
            font=label_font,
        )
        draw.text(
            (value_x, centered_text_y(draw, y, row_h, value, font)),
            value,
            fill="black",
            font=font,
        )
        y += row_h + INFO_LINE_GAP

    TICKETS_DIR.mkdir(parents=True, exist_ok=True)
    ticket_path = TICKETS_DIR / f"{ticket_no}.png"
    canvas.save(ticket_path, format="PNG")

    if SEND_EMAIL:
        send_ticket_email(
            to_email=email, ticket_path=ticket_path, name=name, ticket_no=ticket_no
        )

    return {"ticket_no": ticket_no, "ticket_path": str(ticket_path)}


if __name__ == "__main__":
    print(generate_ticket(name="Test User", email="realJiaoKan@gmail.com", table_no=1))
