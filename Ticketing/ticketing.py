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


def generate_ticket(name, email):
    if not re.match(r"^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$", email):
        raise ValueError(f"Invalid email format: {email}")

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

    # Truncate value if too long
    label1, value1 = "名前：", name
    label1_w = int(draw.textlength(label1, font=label_font))
    label2, value2 = "番号：", ticket_no
    label2_w = int(draw.textlength(label2, font=label_font))
    label_w = max(label1_w, label2_w)
    value_x = TICKET_INFO_X + label_w
    max_value_width = (QR_X - 20) - value_x
    value1 = truncate_long_text(
        draw=draw, text=value1, font=value_font, max_width=max_value_width
    )
    value2 = truncate_long_text(
        draw=draw, text=value2, font=no_font, max_width=max_value_width
    )

    line1_label_bbox = draw.textbbox((0, 0), label1, font=label_font)
    line1_value_bbox = draw.textbbox((0, 0), value1, font=value_font)
    line1_label_h = line1_label_bbox[3] - line1_label_bbox[1]
    line1_value_h = line1_value_bbox[3] - line1_value_bbox[1]
    line1_h = max(line1_label_h, line1_value_h)

    line2_label_bbox = draw.textbbox((0, 0), label2, font=label_font)
    line2_value_bbox = draw.textbbox((0, 0), value2, font=no_font)
    line2_label_h = line2_label_bbox[3] - line2_label_bbox[1]
    line2_value_h = line2_value_bbox[3] - line2_value_bbox[1]
    line2_h = max(line2_label_h, line2_value_h)

    total_h = line1_h + INFO_LINE_GAP + line2_h
    start_y = TICKET_INFO_Y + (TICKET_INFO_HEIGHT - total_h) // 2
    x = TICKET_INFO_X

    y1 = start_y
    draw.text(
        (x, centered_text_y(draw, y1, line1_h, label1, label_font)),
        label1,
        fill="black",
        font=label_font,
    )
    draw.text(
        (value_x, centered_text_y(draw, y1, line1_h, value1, value_font)),
        value1,
        fill="black",
        font=value_font,
    )
    y2 = start_y + line1_h + INFO_LINE_GAP
    draw.text(
        (x, centered_text_y(draw, y2, line2_h, label2, label_font)),
        label2,
        fill="black",
        font=label_font,
    )
    draw.text(
        (value_x, centered_text_y(draw, y2, line2_h, value2, no_font)),
        value2,
        fill="black",
        font=no_font,
    )

    TICKETS_DIR.mkdir(parents=True, exist_ok=True)
    ticket_path = TICKETS_DIR / f"{ticket_no}.png"
    canvas.save(ticket_path, format="PNG")

    if SEND_EMAIL:
        send_ticket_email(
            to_email=email, ticket_path=ticket_path, name=name, ticket_no=ticket_no
        )

    return {"ticket_no": ticket_no, "ticket_path": str(ticket_path)}


if __name__ == "__main__":
    print(generate_ticket(name="Test User", email="realJiaoKan@gmail.com"))
