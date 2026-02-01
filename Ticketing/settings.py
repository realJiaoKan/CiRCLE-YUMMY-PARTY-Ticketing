from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
SEND_EMAIL = False

# Template
TEMPLATE_PATH = BASE_DIR / "Ticketing" / "Assets" / "template.png"
TICKETS_DIR = BASE_DIR / "Ticketing" / "Tickets"

# Data
PRIVATE_KEY_PATH = BASE_DIR / "Ticketing" / "private.key"
NO_PREFIX = "CYP"

# Ticket info box
TICKET_INFO_WIDTH = 750
TICKET_INFO_HEIGHT = 300
TICKET_INFO_X = 1657
TICKET_INFO_Y = 526

# Info
INFO_LABEL_FONT_PATH = BASE_DIR / "Ticketing" / "Assets" / "UDDigiKyokashoN-R.ttc"
INFO_VALUE_FONT_PATH = BASE_DIR / "Ticketing" / "Assets" / "HanziPen SC.ttf"
INFO_NO_FONT_PATH = BASE_DIR / "Ticketing" / "Assets" / "Consolas-Regular.ttf"
INFO_LABEL_FONT_SIZE = 50
INFO_VALUE_FONT_SIZE = 50
INFO_X = TICKET_INFO_X
INFO_LINE_GAP = 20

# QR Code
QR_SIZE = 250
QR_BOX_SIZE = 12
QR_BORDER = 2
QR_X = TICKET_INFO_X + TICKET_INFO_WIDTH - QR_SIZE
QR_Y = TICKET_INFO_Y + (TICKET_INFO_HEIGHT - QR_SIZE) // 2
