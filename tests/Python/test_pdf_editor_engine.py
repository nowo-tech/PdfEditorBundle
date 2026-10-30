"""Tests for engine/pdf_editor_engine.py (REQ-TEST-004). Run with `make test-python`."""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "engine"))

import pdf_editor_engine as engine  # noqa: E402

fitz = engine.fitz


def make_pdf(path: Path, pages: int = 1, form: bool = False, image: Path | None = None, xobject: bool = False) -> Path:
    doc = fitz.open()
    for i in range(pages):
        page = doc.new_page(width=595, height=842)
        page.insert_text(fitz.Point(72, 100), f"Hello page {i + 1}", fontsize=12)
        if i == 0:
            page.insert_text(fitz.Point(72, 400), "CONFIDENTIAL", fontsize=40)
            page.insert_text(fitz.Point(72, 200), "   ", fontsize=12)
    if form:
        widget = fitz.Widget()
        widget.field_name = "name"
        widget.field_type = fitz.PDF_WIDGET_TYPE_TEXT
        widget.rect = fitz.Rect(72, 500, 272, 520)
        widget.field_value = "Ada"
        doc[0].add_widget(widget)
    if image is not None:
        doc[0].insert_image(fitz.Rect(0, 0, 595, 842), filename=str(image))
    if xobject:
        src = fitz.open()
        src.new_page(width=100, height=100).insert_text(fitz.Point(10, 50), "stamp")
        doc[0].show_pdf_page(fitz.Rect(400, 700, 500, 800), src, 0)
        src.close()
    doc.save(str(path))
    doc.close()
    return path


def make_png(path: Path) -> Path:
    pix = fitz.Pixmap(fitz.csRGB, fitz.IRect(0, 0, 20, 20), False)
    pix.clear_with(200)
    pix.save(str(path))
    return path


@pytest.fixture()
def workspace(tmp_path: Path) -> Path:
    return tmp_path


def test_helpers(workspace: Path) -> None:
    assert engine.as_rect([1, 2, 3, 4]) == fitz.Rect(1, 2, 3, 4)
    with pytest.raises(ValueError):
        engine.as_rect([1, 2, 3])
    pdf = make_pdf(workspace / "a.pdf")
    doc = fitz.open(str(pdf))
    with pytest.raises(ValueError):
        engine.load_page(doc, 0)
    with pytest.raises(ValueError):
        engine.load_page(doc, 2)
    doc.close()


def test_resolve_add_image_path(workspace: Path) -> None:
    pdf = workspace / "doc.pdf"
    (workspace / "img.png").write_bytes(b"x")
    assert engine._resolve_add_image_path("img.png", str(pdf)) == os.path.realpath(workspace / "img.png")
    with pytest.raises(ValueError):
        engine._resolve_add_image_path("../outside.png", str(pdf))
    with pytest.raises(ValueError):
        engine._resolve_add_image_path("/etc/passwd", str(pdf))


def test_inspect_detects_text_fields_images_and_watermarks(workspace: Path) -> None:
    png = make_png(workspace / "bg.png")
    pdf = make_pdf(workspace / "a.pdf", pages=2, form=True, image=png)
    result = engine.inspect_pdf(str(pdf))
    assert result["pageCount"] == 2
    assert result["isForm"] is True
    first = result["pages"][0]
    assert any(t["text"] == "Hello page 1" for t in first["texts"])
    assert first["fields"][0]["name"] == "name"
    assert first["images"][0]["kind"] == "image"
    reasons = {w["reason"] for w in result["watermarks"]}
    assert {"label_text", "large_image_overlay"} <= reasons


def test_detect_named_xobject_watermark(workspace: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    pdf = make_pdf(workspace / "x.pdf")
    doc = fitz.open(str(pdf))
    page = doc[0]
    monkeypatch.setattr(type(page), "get_xobjects", lambda self: [("WM1", 7, 0, "")])
    monkeypatch.setattr(type(page), "get_images", lambda self, full=False: [(9,)])
    monkeypatch.setattr(type(page), "get_image_rects", lambda self, xref: [])
    found = engine.detect_watermarks(page, 1, [])
    assert found == [{"id": "p1-x7", "kind": "watermark", "page": 1, "xref": 7, "name": "WM1", "reason": "named_xobject", "bbox": [0.0, 0.0, 0.0, 0.0]}]
    assert engine.extract_images(page, 1)[0]["bbox"] == [0.0, 0.0, 0.0, 0.0]
    doc.close()


def test_apply_all_operations(workspace: Path) -> None:
    png = make_png(workspace / "logo.png")
    pdf = make_pdf(workspace / "in.pdf", pages=3, form=True)
    out = workspace / "out.pdf"
    ops = [
        {"op": "replace_text", "page": 1, "bbox": [70, 85, 300, 105], "text": "Replaced"},
        {"op": "insert_text", "page": 1, "x": 72, "y": 150, "text": "Inserted"},
        {"op": "delete_text", "page": 2, "bbox": [70, 85, 300, 105]},
        {"op": "add_image", "page": 1, "bbox": [300, 300, 340, 340], "path": "logo.png"},
        {"op": "add_acroform_field", "page": 1, "name": "email", "type": "text", "bbox": [72, 550, 272, 570], "value": "a@b.c"},
        {"op": "update_acroform_field", "page": 1, "name": "name", "value": "Grace", "bbox": [72, 500, 300, 520]},
        {"op": "delete_acroform_field", "page": 1, "name": "email"},
        {"op": "add_watermark", "text": "DRAFT", "opacity": 0.2},
        {"op": "remove_watermark", "page": 1, "bbox": [70, 370, 400, 410]},
        {"op": "remove_detected_watermarks"},
        {"op": "rotate_page", "page": 2, "degrees": 90},
        {"op": "insert_blank_page", "after": 1},
        {"op": "add_annotation", "page": 1, "bbox": [72, 600, 272, 640], "text": "Note"},
        {"op": "reorder_pages", "order": [2, 1, 3, 4]},
        {"op": "delete_page", "page": 4},
    ]
    result = engine.apply_ops(str(pdf), ops, str(out))
    assert result == {"applied": len(ops), "pageCount": 3, "out": str(out)}
    inspected = engine.inspect_pdf(str(out))
    assert inspected["pageCount"] == 3


def test_apply_image_operations_and_errors(workspace: Path) -> None:
    png = make_png(workspace / "bg.png")
    pdf = make_pdf(workspace / "img.pdf", image=png)
    xref = engine.inspect_pdf(str(pdf))["pages"][0]["images"][0]["xref"]
    out = workspace / "o.pdf"
    engine.apply_ops(str(pdf), [{"op": "delete_image", "page": 1, "xref": xref}], str(out))
    pdf2 = make_pdf(workspace / "img2.pdf", image=png)
    engine.apply_ops(str(pdf2), [{"op": "remove_watermark", "page": 1, "xref": xref}, {"op": "remove_watermark", "page": 1}], str(out))
    with pytest.raises(ValueError, match="Unsupported operation"):
        engine.apply_ops(str(pdf), [{"op": "nope"}], str(out))
    with pytest.raises(ValueError, match="AcroForm field not found"):
        engine.apply_ops(str(pdf), [{"op": "update_acroform_field", "page": 1, "name": "missing"}], str(out))


def test_cli_commands(workspace: Path, capsys: pytest.CaptureFixture[str]) -> None:
    pdf = make_pdf(workspace / "c.pdf")
    engine.main(["version"])
    assert json.loads(capsys.readouterr().out)["engine"] == "pymupdf"

    engine.main(["inspect", "--pdf", str(pdf)])
    assert json.loads(capsys.readouterr().out)["pageCount"] == 1

    png = workspace / "p.png"
    engine.main(["render", "--pdf", str(pdf), "--page", "1", "--out", str(png), "--dpi", "36"])
    assert json.loads(capsys.readouterr().out)["out"] == str(png)
    assert png.exists()

    ops = workspace / "ops.json"
    ops.write_text(json.dumps({"ops": [{"op": "rotate_page", "page": 1}]}), encoding="utf-8")
    engine.main(["apply", "--pdf", str(pdf), "--ops", str(ops), "--out", str(workspace / "r.pdf")])
    assert json.loads(capsys.readouterr().out)["applied"] == 1

    ops.write_text(json.dumps({"ops": "invalid"}), encoding="utf-8")
    with pytest.raises(SystemExit) as invalid:
        engine.main(["apply", "--pdf", str(pdf), "--ops", str(ops), "--out", str(workspace / "r.pdf")])
    assert invalid.value.code == 1
    assert "must be a list" in json.loads(capsys.readouterr().err)["error"]

    ops.write_text(json.dumps([{"op": "nope"}]), encoding="utf-8")
    with pytest.raises(SystemExit):
        engine.main(["apply", "--pdf", str(pdf), "--ops", str(ops), "--out", str(workspace / "r.pdf")])
    assert "Unsupported operation" in json.loads(capsys.readouterr().err)["error"]


def make_big_page_pdf(path: Path, side: float) -> Path:
    doc = fitz.open()
    doc.new_page(width=side, height=side)
    doc.save(str(path))
    doc.close()
    return path


def test_render_dpi_is_capped_by_max_pixels() -> None:
    assert engine.render_dpi(595, 842, 144, 25_000_000) == 144
    assert engine.render_dpi(9000, 9000, 144, 25_000_000) == 40  # 18000² px at 144 dpi → ≤ 25 MP
    assert engine.render_dpi(200_000, 200_000, 144, 25_000_000) == 1
    assert engine.render_dpi(595, 842, 0, 25_000_000) == 1
    assert engine.render_dpi(9000, 9000, 144, 0) == 144


def test_render_of_a_pixel_bomb_page_stays_within_max_pixels(workspace: Path, capsys: pytest.CaptureFixture[str]) -> None:
    pdf = make_big_page_pdf(workspace / "bomb.pdf", 9000)
    png = workspace / "bomb.png"
    engine.main(["render", "--pdf", str(pdf), "--page", "1", "--out", str(png), "--dpi", "144", "--max-pixels", "1000000"])
    out = json.loads(capsys.readouterr().out)
    assert out["width"] * out["height"] <= 1_000_000
    assert out["dpi"] < 144


def test_render_errors_are_one_json_line(workspace: Path, capsys: pytest.CaptureFixture[str]) -> None:
    pdf = make_pdf(workspace / "one.pdf")
    with pytest.raises(SystemExit) as failed:
        engine.main(["render", "--pdf", str(pdf), "--page", "5", "--out", str(workspace / "x.png")])
    assert failed.value.code == 1
    assert "out of range" in json.loads(capsys.readouterr().err)["error"]


def test_memory_error_is_reported_cleanly(workspace: Path, capsys: pytest.CaptureFixture[str], monkeypatch: pytest.MonkeyPatch) -> None:
    def boom(_args: object) -> None:
        raise MemoryError

    monkeypatch.setattr(engine, "cmd_inspect", boom)
    with pytest.raises(SystemExit):
        engine.main(["inspect", "--pdf", str(workspace / "missing.pdf")])
    assert "memory limit" in json.loads(capsys.readouterr().err)["error"]


def test_insert_blank_page_size_is_bounded(workspace: Path) -> None:
    pdf = make_pdf(workspace / "s.pdf")
    with pytest.raises(ValueError, match="between 1 and 14400"):
        engine.apply_ops(str(pdf), [{"op": "insert_blank_page", "width": 90000, "height": 842}], str(workspace / "o.pdf"))
    with pytest.raises(ValueError, match="height must be between"):
        engine.apply_ops(str(pdf), [{"op": "insert_blank_page", "width": 595, "height": -5}], str(workspace / "o.pdf"))


def test_operation_and_page_counts_are_bounded(workspace: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    pdf = make_pdf(workspace / "c.pdf")
    with pytest.raises(ValueError, match="Too many operations"):
        engine.apply_ops(str(pdf), [{"op": "rotate_page", "page": 1}] * (engine.MAX_OPERATIONS + 1), str(workspace / "o.pdf"))
    monkeypatch.setattr(engine, "MAX_PAGES", 3)
    with pytest.raises(ValueError, match="more than 3 pages"):
        engine.apply_ops(str(pdf), [{"op": "insert_blank_page"}] * 3, str(workspace / "o.pdf"))


def test_apply_memory_limit(monkeypatch: pytest.MonkeyPatch) -> None:
    import resource

    calls: list[tuple[int, tuple[int, int]]] = []
    monkeypatch.setattr(resource, "setrlimit", lambda kind, limits: calls.append((kind, limits)))
    monkeypatch.setattr(resource, "getrlimit", lambda _kind: (resource.RLIM_INFINITY, resource.RLIM_INFINITY))
    engine.apply_memory_limit(0)
    assert calls == []
    engine.apply_memory_limit(512)
    assert calls == [(resource.RLIMIT_AS, (512 * 1024 * 1024, resource.RLIM_INFINITY))]

    calls.clear()
    monkeypatch.setattr(resource, "getrlimit", lambda _kind: (100, 100))
    engine.apply_memory_limit(512)
    assert calls == [(resource.RLIMIT_AS, (100, 100))]


def test_memory_limit_stops_a_pixel_bomb_in_a_real_process(workspace: Path) -> None:
    import subprocess

    pdf = make_big_page_pdf(workspace / "bomb.pdf", 9000)
    script = Path(__file__).resolve().parents[2] / "engine" / "pdf_editor_engine.py"
    result = subprocess.run(
        [sys.executable, str(script), "--memory-limit", "400", "render", "--pdf", str(pdf), "--page", "1",
         "--out", str(workspace / "b.png"), "--dpi", "144", "--max-pixels", "0"],
        capture_output=True, text=True, timeout=120, check=False,
    )
    assert result.returncode == 1
    assert json.loads(result.stderr.strip().splitlines()[-1])["ok"] is False
    assert not (workspace / "b.png").exists()
