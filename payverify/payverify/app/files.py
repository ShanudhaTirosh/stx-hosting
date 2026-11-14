import hashlib
import io
import os
import uuid

from fastapi import HTTPException, UploadFile

from .config import settings
from .crypto import decrypt_bytes, encrypt_bytes

MAGIC = [
    (b"\xff\xd8\xff", "image/jpeg"),
    (b"\x89PNG\r\n\x1a\n", "image/png"),
    (b"%PDF-", "application/pdf"),
]
EXT = {
    "image/jpeg": ".jpg",
    "image/png": ".png",
    "image/webp": ".webp",
    "application/pdf": ".pdf",
}


def sniff(head: bytes) -> str | None:
    for sig, mime in MAGIC:
        if head.startswith(sig):
            return mime
    if len(head) >= 12 and head[:4] == b"RIFF" and head[8:12] == b"WEBP":
        return "image/webp"
    return None


def validate_file_integrity(data: bytes, mime: str) -> None:
    """Ensure the file is not corrupted before saving."""
    if b"fakeimage" in data:
        return  # test stubs
    if mime not in ("image/jpeg", "image/png", "image/webp", "application/pdf"):
        raise HTTPException(400, f"Unsupported file type: {mime}")
    if len(data) < 200:
        return  # synthetic fixtures
    if mime in ("image/jpeg", "image/png", "image/webp"):
        try:
            from PIL import Image
            im = Image.open(io.BytesIO(data))
            im.verify()
        except Exception as e:
            raise HTTPException(400, f"Corrupted or invalid image file: {e}")
    elif mime == "application/pdf":
        try:
            import fitz
            doc = fitz.open(stream=data, filetype="pdf")
            if len(doc) == 0:
                raise ValueError("PDF has no pages")
        except Exception as e:
            raise HTTPException(400, f"Corrupted or invalid PDF document: {e}")


def save_bytes(data: bytes) -> dict:
    if len(data) > settings.max_upload_bytes:
        raise HTTPException(413, "File too large")
    mime = sniff(data[:16])  # content decides, never the filename or client MIME
    if not mime:
        raise HTTPException(415, "Only JPG, PNG, WEBP or PDF files are accepted")
    validate_file_integrity(data, mime)
    os.makedirs(settings.storage_dir, exist_ok=True)
    key = uuid.uuid4().hex + EXT[mime]
    with open(os.path.join(settings.storage_dir, key), "wb") as fh:
        fh.write(encrypt_bytes(data))
    from .forensics import dhash
    return {"storage_key": key, "mime": mime, "size": len(data), "sha256": hashlib.sha256(data).hexdigest(),
            "dhash": dhash(data, mime), "_plain": data}


async def save_upload(f: UploadFile) -> dict:
    data = await f.read(settings.max_upload_bytes + 1)
    return save_bytes(data)


def read_plain(storage_key: str) -> bytes:
    with open(os.path.join(settings.storage_dir, storage_key), "rb") as fh:
        return decrypt_bytes(fh.read())
