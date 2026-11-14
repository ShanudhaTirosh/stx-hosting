"""Image and document preprocessing for universal payment slip verification.
Handles EXIF auto-rotation, adaptive lighting/contrast enhancement for dark/blurry slips,
downscaling large camera captures, and converting all PDF pages to high-resolution images.
"""
import io
import logging
from typing import List, Tuple

log = logging.getLogger("payverify.preprocess")

MAX_DIMENSION = 2048  # Clamps large phone photos to maintain detail without payload bloating
MAX_PDF_PAGES = 10    # Safety limit to process all receipt pages while preventing DOS


class PreprocessError(ValueError):
    pass


def assess_image_quality(im_or_bytes) -> dict:
    """Assess brightness, contrast, and sharpness metrics of an image."""
    from PIL import Image, ImageFilter, ImageStat
    if isinstance(im_or_bytes, (bytes, bytearray)):
        im = Image.open(io.BytesIO(im_or_bytes))
    else:
        im = im_or_bytes
    gray = im.convert("L")
    stat = ImageStat.Stat(gray)
    mean_lum = stat.mean[0] if stat.mean else 128.0
    std_lum = stat.stddev[0] if stat.stddev else 50.0
    try:
        edges = gray.filter(ImageFilter.FIND_EDGES)
        edge_stat = ImageStat.Stat(edges)
        sharpness = edge_stat.var[0] if edge_stat.var else 0.0
    except Exception:
        sharpness = float(std_lum)
    return {
        "brightness": mean_lum,
        "contrast": std_lum,
        "sharpness": sharpness,
    }


def preprocess_image(data: bytes, mime: str) -> Tuple[bytes, str]:
    """Auto-rotate EXIF orientation, enhance contrast/lighting if faint/dark,
    and downscale if dimensions exceed MAX_DIMENSION.
    """
    try:
        from PIL import Image, ImageEnhance, ImageOps, ImageStat
    except ImportError as e:
        log.warning("Pillow not installed; skipping image preprocessing: %s", e)
        return data, mime

    try:
        im = Image.open(io.BytesIO(data))
    except Exception as e:
        raise PreprocessError(f"Failed to decode image data: {e}") from e

    # 1. EXIF Auto-rotation (fixes phone photos taken in portrait/landscape)
    try:
        transposed = ImageOps.exif_transpose(im)
        if transposed is not None:
            im = transposed
    except Exception as e:
        log.debug("exif_transpose error: %s", e)

    # 2. Resizing large phone camera photos
    w, h = im.size
    if max(w, h) > MAX_DIMENSION:
        ratio = MAX_DIMENSION / float(max(w, h))
        new_size = (int(w * ratio), int(h * ratio))
        resample = getattr(Image, "Resampling", Image).LANCZOS
        im = im.resize(new_size, resample=resample)

    # 3. Adaptive lighting and contrast enhancement for blurry / dark photos
    try:
        gray = im.convert("L")
        stat = ImageStat.Stat(gray)
        mean_lum = stat.mean[0] if stat.mean else 128
        std_lum = stat.stddev[0] if stat.stddev else 50

        # Enhance dark slips
        if mean_lum < 95:
            im = ImageEnhance.Brightness(im).enhance(1.25)
        # Enhance washed-out or low-contrast slips
        if std_lum < 45:
            im = ImageEnhance.Contrast(im).enhance(1.3)
        # Mild sharpness enhancement to clarify faint text/dots
        im = ImageEnhance.Sharpness(im).enhance(1.15)
    except Exception as e:
        log.debug("Image enhancement error: %s", e)

    # 4. Save optimized bytes
    out_format = "PNG" if mime == "image/png" else "JPEG"
    out_mime = "image/png" if out_format == "PNG" else "image/jpeg"

    # Convert mode if saving JPEG
    if out_format == "JPEG" and im.mode in ("RGBA", "P"):
        im = im.convert("RGB")

    buf = io.BytesIO()
    if out_format == "JPEG":
        im.save(buf, format="JPEG", quality=90, optimize=True)
    else:
        im.save(buf, format="PNG", optimize=True)

    return buf.getvalue(), out_mime


def pdf_to_images(data: bytes, max_pages: int = MAX_PDF_PAGES) -> List[Tuple[bytes, str]]:
    """Convert all PDF pages into preprocessed PNG images."""
    try:
        import fitz  # PyMuPDF
    except ImportError as e:
        raise PreprocessError(f"PyMuPDF not installed; cannot read PDF: {e}") from e

    try:
        doc = fitz.open(stream=data, filetype="pdf")
    except Exception as e:
        raise PreprocessError(f"Corrupted or unreadable PDF: {e}") from e

    if len(doc) == 0:
        raise PreprocessError("PDF document contains no pages")

    pages: List[Tuple[bytes, str]] = []
    num_pages = min(len(doc), max_pages)

    for i in range(num_pages):
        page = doc[i]
        pix = page.get_pixmap(dpi=200)
        png_bytes = pix.tobytes("png")
        # Preprocess page image for optimal readability
        proc_bytes, mime = preprocess_image(png_bytes, "image/png")
        pages.append((proc_bytes, mime))

    return pages
