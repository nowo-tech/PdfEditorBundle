#!/usr/bin/env python3
"""PDF document engine for Nowo PdfEditorBundle.

Required runtime: Python 3.9+ and PyMuPDF (`pymupdf`) — free, no paid SDK.
The PHP bundle invokes this process with an explicit timeout (REQ-RUNTIME-001).

Commands:
  version
  inspect --pdf PATH
  render  --pdf PATH --page N --out PATH [--dpi 144]
  apply   --pdf PATH --ops PATH --out PATH
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from typing import Any

try:
    import pymupdf as fitz
except ImportError as exc:  # pragma: no cover - environment
    print(
        json.dumps(
            {
                "ok": False,
                "error": "PyMuPDF (pymupdf) is required. Install: python3 -m pip install pymupdf",
            }
        ),
        file=sys.stderr,
    )
    raise SystemExit(2) from exc


WIDGET_TYPES = {
    "text": fitz.PDF_WIDGET_TYPE_TEXT,
    "checkbox": fitz.PDF_WIDGET_TYPE_CHECKBOX,
    "radio": fitz.PDF_WIDGET_TYPE_RADIOBUTTON,
    "choice": fitz.PDF_WIDGET_TYPE_COMBOBOX,
    "list": fitz.PDF_WIDGET_TYPE_LISTBOX,
    "signature": fitz.PDF_WIDGET_TYPE_SIGNATURE,
    "button": fitz.PDF_WIDGET_TYPE_BUTTON,
}

WATERMARK_TOKENS = ("draft", "copy", "confidential", "sample", "watermark", "preview")


def emit_ok(payload: dict[str, Any]) -> None:
    print(json.dumps({"ok": True, **payload}, ensure_ascii=False))


def fail(message: str, code: int = 1) -> None:
    print(json.dumps({"ok": False, "error": message}, ensure_ascii=False), file=sys.stderr)
    raise SystemExit(code)


def as_rect(values: list[Any]) -> fitz.Rect:
    if len(values) != 4:
        raise ValueError("bbox/rect must be [x0, y0, x1, y1]")
    return fitz.Rect(float(values[0]), float(values[1]), float(values[2]), float(values[3]))


def load_page(doc: fitz.Document, number: int) -> fitz.Page:
    index = number - 1
    if index < 0 or index >= doc.page_count:
        raise ValueError(f"Page {number} is out of range (1..{doc.page_count}).")
    return doc.load_page(index)


def extract_spans(page: fitz.Page, page_no: int) -> list[dict[str, Any]]:
    items: list[dict[str, Any]] = []
    data = page.get_text("dict")
    index = 0
    for block in data.get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            for span in line.get("spans", []):
                text = span.get("text") or ""
                if text.strip() == "":
                    continue
                items.append(
                    {
                        "id": f"p{page_no}-t{index}",
                        "kind": "text",
                        "page": page_no,
                        "text": text,
                        "bbox": [round(float(v), 2) for v in span["bbox"]],
                        "size": round(float(span.get("size") or 0), 2),
                        "font": span.get("font") or "",
                        "color": int(span.get("color") or 0),
                    }
                )
                index += 1
    return items


def extract_images(page: fitz.Page, page_no: int) -> list[dict[str, Any]]:
    items: list[dict[str, Any]] = []
    for idx, img in enumerate(page.get_images(full=True)):
        xref = int(img[0])
        rects = page.get_image_rects(xref)
        bbox = [round(float(v), 2) for v in rects[0]] if rects else [0.0, 0.0, 0.0, 0.0]
        items.append(
            {
                "id": f"p{page_no}-i{idx}",
                "kind": "image",
                "page": page_no,
                "xref": xref,
                "bbox": bbox,
            }
        )
    return items


def extract_fields(page: fitz.Page, page_no: int) -> list[dict[str, Any]]:
    items: list[dict[str, Any]] = []
    for idx, widget in enumerate(page.widgets() or []):
        items.append(
            {
                "id": f"p{page_no}-f{idx}",
                "kind": "acroform",
                "page": page_no,
                "name": widget.field_name or "",
                "type": widget.field_type_string or "",
                "value": widget.field_value if widget.field_value is not None else "",
                "bbox": [round(float(v), 2) for v in widget.rect],
            }
        )
    return items


def detect_watermarks(page: fitz.Page, page_no: int, text_items: list[dict[str, Any]]) -> list[dict[str, Any]]:
    found: list[dict[str, Any]] = []
    page_area = abs(page.rect.width * page.rect.height) or 1.0

    for span in text_items:
        bbox = span["bbox"]
        width = abs(bbox[2] - bbox[0])
        height = abs(bbox[3] - bbox[1])
        text_l = str(span["text"]).lower()
        if any(token in text_l for token in WATERMARK_TOKENS) or (height > 40 and width > 80 and height / max(width, 1.0) > 0.35):
            if any(token in text_l for token in WATERMARK_TOKENS) or span["size"] >= 28:
                found.append({**span, "kind": "watermark", "reason": "label_text"})

    for idx, img in enumerate(page.get_images(full=True)):
        xref = int(img[0])
        rects = page.get_image_rects(xref)
        if not rects:
            continue
        rect = rects[0]
        coverage = abs(rect.width * rect.height) / page_area
        if coverage >= 0.35:
            found.append(
                {
                    "id": f"p{page_no}-w{idx}",
                    "kind": "watermark",
                    "page": page_no,
                    "xref": xref,
                    "bbox": [round(float(v), 2) for v in rect],
                    "reason": "large_image_overlay",
                    "coverage": round(coverage, 3),
                }
            )

    for name, xref, *_rest in page.get_xobjects():
        label = str(name).lower()
        if "stamp" in label or "watermark" in label or label in {"wm", "wm1"}:
            found.append(
                {
                    "id": f"p{page_no}-x{xref}",
                    "kind": "watermark",
                    "page": page_no,
                    "xref": int(xref),
                    "name": str(name),
                    "reason": "named_xobject",
                    "bbox": [0.0, 0.0, 0.0, 0.0],
                }
            )

    return found


def inspect_pdf(path: str) -> dict[str, Any]:
    doc = fitz.open(path)
    try:
        pages: list[dict[str, Any]] = []
        watermarks: list[dict[str, Any]] = []
        for i, page in enumerate(doc):
            page_no = i + 1
            texts = extract_spans(page, page_no)
            images = extract_images(page, page_no)
            fields = extract_fields(page, page_no)
            detected = detect_watermarks(page, page_no, texts)
            watermarks.extend(detected)
            pages.append(
                {
                    "number": page_no,
                    "width": round(page.rect.width, 2),
                    "height": round(page.rect.height, 2),
                    "rotation": page.rotation,
                    "texts": texts,
                    "images": images,
                    "fields": fields,
                }
            )
        return {
            "pageCount": doc.page_count,
            "metadata": doc.metadata or {},
            "isForm": bool(doc.is_form_pdf),
            "isEncrypted": bool(doc.is_encrypted),
            "pages": pages,
            "watermarks": watermarks,
        }
    finally:
        doc.close()


def apply_ops(pdf_path: str, ops: list[dict[str, Any]], out_path: str) -> dict[str, Any]:
    doc = fitz.open(pdf_path)
    applied = 0
    try:
        for raw in ops:
            op = str(raw.get("op") or "")
            if op == "replace_text":
                page = load_page(doc, int(raw["page"]))
                rect = as_rect(list(raw["bbox"]))
                page.add_redact_annot(rect, fill=(1, 1, 1))
                page.apply_redactions(images=fitz.PDF_REDACT_IMAGE_NONE)
                page.insert_textbox(
                    rect,
                    str(raw.get("text") or ""),
                    fontsize=float(raw.get("fontSize") or 11),
                    fontname=str(raw.get("font") or "helv"),
                    color=(0, 0, 0),
                    align=fitz.TEXT_ALIGN_LEFT,
                )
            elif op == "insert_text":
                page = load_page(doc, int(raw["page"]))
                page.insert_text(
                    fitz.Point(float(raw["x"]), float(raw["y"])),
                    str(raw.get("text") or ""),
                    fontsize=float(raw.get("fontSize") or 12),
                    fontname=str(raw.get("font") or "helv"),
                    color=(0, 0, 0),
                )
            elif op == "delete_text":
                page = load_page(doc, int(raw["page"]))
                page.add_redact_annot(as_rect(list(raw["bbox"])), fill=(1, 1, 1))
                page.apply_redactions(images=fitz.PDF_REDACT_IMAGE_NONE)
            elif op == "add_image":
                page = load_page(doc, int(raw["page"]))
                page.insert_image(as_rect(list(raw["bbox"])), filename=str(raw["path"]))
            elif op == "delete_image":
                page = load_page(doc, int(raw["page"]))
                page.delete_image(int(raw["xref"]))
            elif op == "add_acroform_field":
                page = load_page(doc, int(raw["page"]))
                widget = fitz.Widget()
                widget.field_name = str(raw["name"])
                widget.field_type = WIDGET_TYPES.get(str(raw.get("type") or "text"), fitz.PDF_WIDGET_TYPE_TEXT)
                widget.rect = as_rect(list(raw["bbox"]))
                widget.field_value = str(raw.get("value") or "")
                widget.text_fontsize = float(raw.get("fontSize") or 11)
                page.add_widget(widget)
            elif op == "update_acroform_field":
                page = load_page(doc, int(raw["page"]))
                name = str(raw["name"])
                updated = False
                for widget in page.widgets() or []:
                    if widget.field_name != name:
                        continue
                    if "value" in raw:
                        widget.field_value = str(raw["value"])
                    if "bbox" in raw:
                        widget.rect = as_rect(list(raw["bbox"]))
                    widget.update()
                    updated = True
                    break
                if not updated:
                    raise ValueError(f"AcroForm field not found: {name}")
            elif op == "delete_acroform_field":
                page = load_page(doc, int(raw["page"]))
                name = str(raw["name"])
                for widget in list(page.widgets() or []):
                    if widget.field_name == name:
                        page.delete_widget(widget)
                        break
            elif op == "add_watermark":
                text = str(raw.get("text") or "WATERMARK")
                opacity = float(raw.get("opacity") or 0.18)
                for page in doc:
                    rect = page.rect
                    fontsize = max(28.0, min(rect.width, rect.height) / 8.0)
                    writer = fitz.TextWriter(rect)
                    writer.append(
                        fitz.Point(rect.width * 0.18, rect.height * 0.55),
                        text,
                        fontsize=fontsize,
                        font=fitz.Font("helv"),
                    )
                    writer.write_text(page, color=(0.45, 0.45, 0.45), opacity=opacity)
            elif op == "remove_watermark":
                page = load_page(doc, int(raw["page"]))
                if raw.get("bbox"):
                    page.add_redact_annot(as_rect(list(raw["bbox"])), fill=(1, 1, 1))
                    page.apply_redactions(images=fitz.PDF_REDACT_IMAGE_REMOVE)
                elif "xref" in raw:
                    page.delete_image(int(raw["xref"]))
            elif op == "remove_detected_watermarks":
                for page in doc:
                    texts = extract_spans(page, page.number + 1)
                    for wm in detect_watermarks(page, page.number + 1, texts):
                        bbox = wm.get("bbox") or [0, 0, 0, 0]
                        if bbox != [0.0, 0.0, 0.0, 0.0] and bbox != [0, 0, 0, 0]:
                            page.add_redact_annot(as_rect(list(bbox)), fill=(1, 1, 1))
                    page.apply_redactions(images=fitz.PDF_REDACT_IMAGE_REMOVE)
            elif op == "rotate_page":
                page = load_page(doc, int(raw["page"]))
                page.set_rotation(int(raw.get("degrees") or 90))
            elif op == "delete_page":
                doc.delete_page(int(raw["page"]) - 1)
            elif op == "insert_blank_page":
                after = int(raw.get("after") or doc.page_count)
                doc.new_page(
                    pno=after,
                    width=float(raw.get("width") or 595),
                    height=float(raw.get("height") or 842),
                )
            elif op == "reorder_pages":
                order = [int(n) - 1 for n in raw["order"]]
                doc.select(order)
            elif op == "add_annotation":
                page = load_page(doc, int(raw["page"]))
                annot = page.add_freetext_annot(
                    as_rect(list(raw["bbox"])),
                    str(raw.get("text") or ""),
                    fontsize=float(raw.get("fontSize") or 10),
                )
                annot.update()
            else:
                raise ValueError(f"Unsupported operation: {op}")
            applied += 1
        doc.save(out_path, deflate=True, garbage=3, incremental=False)
        return {"applied": applied, "pageCount": doc.page_count, "out": out_path}
    finally:
        doc.close()


def cmd_version(_args: argparse.Namespace) -> None:
    emit_ok({"engine": "pymupdf", "pymupdf": fitz.version[0], "python": sys.version.split()[0]})


def cmd_inspect(args: argparse.Namespace) -> None:
    emit_ok(inspect_pdf(args.pdf))


def cmd_render(args: argparse.Namespace) -> None:
    doc = fitz.open(args.pdf)
    try:
        page = load_page(doc, args.page)
        pix = page.get_pixmap(dpi=args.dpi, alpha=False)
        tmp = f"{args.out}.{os.getpid()}.png"
        pix.save(tmp)
        os.replace(tmp, args.out)
        emit_ok({"page": args.page, "width": pix.width, "height": pix.height, "out": args.out})
    finally:
        doc.close()


def cmd_apply(args: argparse.Namespace) -> None:
    with open(args.ops, encoding="utf-8") as handle:
        payload = json.load(handle)
    ops = payload.get("ops") if isinstance(payload, dict) else payload
    if not isinstance(ops, list):
        fail('Operations JSON must be a list or {"ops": [...]}.')
    try:
        result = apply_ops(args.pdf, ops, args.out)
    except Exception as exc:  # noqa: BLE001 - surface engine errors to PHP
        fail(str(exc))
    emit_ok(result)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="pdf_editor_engine")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("version")

    inspect = sub.add_parser("inspect")
    inspect.add_argument("--pdf", required=True)

    render = sub.add_parser("render")
    render.add_argument("--pdf", required=True)
    render.add_argument("--page", type=int, required=True)
    render.add_argument("--out", required=True)
    render.add_argument("--dpi", type=int, default=144)

    apply_cmd = sub.add_parser("apply")
    apply_cmd.add_argument("--pdf", required=True)
    apply_cmd.add_argument("--ops", required=True)
    apply_cmd.add_argument("--out", required=True)
    return parser


def main(argv: list[str] | None = None) -> None:
    parser = build_parser()
    args = parser.parse_args(argv)
    if args.command == "version":
        cmd_version(args)
    elif args.command == "inspect":
        cmd_inspect(args)
    elif args.command == "render":
        cmd_render(args)
    elif args.command == "apply":
        cmd_apply(args)
    else:  # pragma: no cover
        fail(f"Unknown command: {args.command}")


if __name__ == "__main__":
    main()
