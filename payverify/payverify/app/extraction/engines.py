"""OCR engines and page rasterisation. Heavy libraries are imported lazily so the API runs without them.
PaddleOCR / Tesseract / PyMuPDF adapters are UNTESTED here (no models installed in the build sandbox)."""
from typing import Protocol

MAX_PAGES = 3


class ExtractionError(Exception):
    pass


class OcrEngine(Protocol):
    name: str

    def read(self, image_bytes: bytes) -> str: ...


class NullOcr:
    name = "none"

    def read(self, image_bytes: bytes) -> str:
        return ""


class TesseractOcr:
    name = "tesseract"

    def read(self, image_bytes: bytes) -> str:
        try:
            import io

            import pytesseract
            from PIL import Image
        except ImportError as e:
            raise ExtractionError("pytesseract/Pillow not installed") from e
        return pytesseract.image_to_string(Image.open(io.BytesIO(image_bytes)))


class PaddleOcr:
    name = "paddle"
    _ocr = None

    def read(self, image_bytes: bytes) -> str:
        try:
            import io

            import numpy as np
            from paddleocr import PaddleOCR
            from PIL import Image
        except ImportError as e:
            raise ExtractionError("paddleocr/numpy/Pillow not installed") from e
        if PaddleOcr._ocr is None:
            PaddleOcr._ocr = PaddleOCR(lang="en")
        arr = np.array(Image.open(io.BytesIO(image_bytes)).convert("RGB"))
        res = PaddleOcr._ocr.ocr(arr)
        lines: list[str] = []

        def walk(node):  # result shape differs across PaddleOCR versions; collect any (text, score) pairs
            if isinstance(node, (list, tuple)):
                if len(node) == 2 and isinstance(node[0], str) and isinstance(node[1], (int, float)):
                    lines.append(node[0])
                else:
                    for n in node:
                        walk(n)
            elif isinstance(node, dict):
                for t in node.get("rec_texts", []) or []:
                    lines.append(str(t))
        walk(res)
        return "\n".join(lines)


def build_ocr(name: str) -> OcrEngine:
    return {"paddle": PaddleOcr, "tesseract": TesseractOcr}.get(name, NullOcr)()


def to_images(data: bytes, mime: str) -> list[tuple[bytes, str]]:
    """Return [(image_bytes, media_type)] pages ready for OCR / the model."""
    if mime in ("image/jpeg", "image/png"):
        return [(data, mime)]
    try:
        import fitz  # PyMuPDF
    except ImportError as e:
        raise ExtractionError("PyMuPDF not installed; cannot read PDF") from e
    doc = fitz.open(stream=data, filetype="pdf")
    return [(doc[i].get_pixmap(dpi=200).tobytes("png"), "image/png") for i in range(min(len(doc), MAX_PAGES))]
