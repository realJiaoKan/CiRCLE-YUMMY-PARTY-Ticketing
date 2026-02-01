import json
import mimetypes
import smtplib
from email.message import EmailMessage
from pathlib import Path

from settings import BASE_DIR


def send_ticket_email(*, to_email: str, ticket_path: Path, name: str, ticket_no: str):
    # Load configuration
    config = json.loads(
        (BASE_DIR / "Ticketing" / "config.json").read_text(encoding="utf-8")
    )
    smtp = config.get("smtp")
    email = config.get("email")

    smtp_host = smtp.get("host")
    smtp_port = int(smtp.get("port"))
    smtp_username = smtp.get("username")
    smtp_password = smtp.get("password")

    # Compose email
    msg = EmailMessage()
    msg["Subject"] = email.get("subject")
    msg["From"] = f"{email.get('from_name')} " f"<{email.get('from')}>"
    msg["To"] = to_email
    email_cc = email.get("cc")
    if email_cc:
        msg["Cc"] = email_cc

    html = f"""\
<!doctype html>
<html>
  <body style="font-family: Arial, sans-serif;">
    <p>Hi {name},</p>
    <p>Your ticket is attached. Please keep it safe.</p>
    <p style="color:#666;">Ticket No: {ticket_no}</p>
  </body>
</html>
"""
    msg.set_content(
        f"Hi {name},\n\nYour ticket is attached.\n\nTicket No: {ticket_no}\n"
    )
    msg.add_alternative(html, subtype="html")

    ticket = ticket_path.read_bytes()
    ctype, _ = mimetypes.guess_type(ticket_path.name)
    if not ctype:
        ctype = "application/octet-stream"
    maintype, subtype = ctype.split("/", 1)
    msg.add_attachment(
        ticket, maintype=maintype, subtype=subtype, filename=ticket_path.name
    )

    # Send email
    if smtp.get("use_ssl", False):
        with smtplib.SMTP_SSL(smtp_host, smtp_port) as server:
            if smtp_username:
                server.login(smtp_username, smtp_password)
            server.send_message(msg)
        return

    with smtplib.SMTP(smtp_host, smtp_port) as server:
        server.ehlo()
        if smtp.get("use_starttls", True):
            server.starttls()
            server.ehlo()
        if smtp_username:
            server.login(smtp_username, smtp_password)
        server.send_message(msg)
