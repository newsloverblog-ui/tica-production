from pathlib import Path
import re

from docx import Document
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Inches, Pt, RGBColor


ROOT = Path(__file__).resolve().parent
SOURCE = ROOT / "ticketing-platform-product-architecture.md"
OUTPUT = ROOT / "TICA-UI-Test-Dashboard-Product-Architecture.docx"

NAVY = "0B2447"
BLUE = "2563D9"
PALE_BLUE = "EDF4FF"
PALE_GRAY = "F5F7FA"
GRID = "D9E1EC"
TEXT = RGBColor(15, 23, 42)
MUTED = RGBColor(71, 85, 105)


def shade(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def borders(cell, color=GRID, size="5"):
    tc_pr = cell._tc.get_or_add_tcPr()
    edges = tc_pr.first_child_found_in("w:tcBorders")
    if edges is None:
        edges = OxmlElement("w:tcBorders")
        tc_pr.append(edges)
    for edge_name in ("top", "left", "bottom", "right", "insideH", "insideV"):
        edge = edges.find(qn(f"w:{edge_name}"))
        if edge is None:
            edge = OxmlElement(f"w:{edge_name}")
            edges.append(edge)
        edge.set(qn("w:val"), "single")
        edge.set(qn("w:sz"), size)
        edge.set(qn("w:color"), color)


def set_cell_margins(cell, top=70, start=90, bottom=70, end=90):
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for name, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{name}"))
        if node is None:
            node = OxmlElement(f"w:{name}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def repeat_header(row):
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def prevent_row_split(row):
    tr_pr = row._tr.get_or_add_trPr()
    cant_split = OxmlElement("w:cantSplit")
    cant_split.set(qn("w:val"), "true")
    tr_pr.append(cant_split)


def keep_with_next(paragraph):
    p_pr = paragraph._p.get_or_add_pPr()
    keep = OxmlElement("w:keepNext")
    p_pr.append(keep)


def add_page_number(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = paragraph.add_run("")
    run.font.size = Pt(8)
    run.font.color.rgb = MUTED
    fld_char1 = OxmlElement("w:fldChar")
    fld_char1.set(qn("w:fldCharType"), "begin")
    instr_text = OxmlElement("w:instrText")
    instr_text.set(qn("xml:space"), "preserve")
    instr_text.text = "PAGE"
    fld_char2 = OxmlElement("w:fldChar")
    fld_char2.set(qn("w:fldCharType"), "end")
    run._r.append(fld_char1)
    run._r.append(instr_text)
    run._r.append(fld_char2)


def add_inline(paragraph, text):
    parts = re.split(r"(\*\*[^*]+\*\*|`[^`]+`)", text)
    for part in parts:
        if not part:
            continue
        if part.startswith("**") and part.endswith("**"):
            run = paragraph.add_run(part[2:-2])
            run.bold = True
        elif part.startswith("`") and part.endswith("`"):
            run = paragraph.add_run(part[1:-1])
            run.font.name = "Aptos Mono"
            run.font.size = Pt(8.5)
        else:
            paragraph.add_run(part)


def parse_table(lines, start):
    rows = []
    i = start
    while i < len(lines) and lines[i].strip().startswith("|"):
        raw = lines[i].strip().strip("|")
        rows.append([c.strip() for c in raw.split("|")])
        i += 1
    if len(rows) > 1 and all(re.fullmatch(r":?-{3,}:?", c.replace(" ", "")) for c in rows[1]):
        rows.pop(1)
    return rows, i


def add_table(doc, rows):
    if not rows:
        return
    cols = max(len(r) for r in rows)
    table = doc.add_table(rows=len(rows), cols=cols)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = True
    table.style = "Table Grid"
    for r_idx, row_data in enumerate(rows):
        row = table.rows[r_idx]
        prevent_row_split(row)
        if r_idx == 0:
            repeat_header(row)
        for c_idx in range(cols):
            cell = row.cells[c_idx]
            value = row_data[c_idx] if c_idx < len(row_data) else ""
            cell.text = ""
            p = cell.paragraphs[0]
            add_inline(p, value)
            p.paragraph_format.space_after = Pt(0)
            p.paragraph_format.line_spacing = 1.0
            for run in p.runs:
                run.font.name = "Aptos"
                run.font.size = Pt(8.1 if cols >= 5 else 8.5)
                run.font.color.rgb = TEXT
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            set_cell_margins(cell)
            borders(cell)
            if r_idx == 0:
                shade(cell, NAVY)
                for run in p.runs:
                    run.bold = True
                    run.font.color.rgb = RGBColor(255, 255, 255)
            elif r_idx % 2 == 0:
                shade(cell, PALE_GRAY)
    doc.add_paragraph().paragraph_format.space_after = Pt(1)


def configure(doc):
    section = doc.sections[0]
    section.orientation = WD_ORIENT.LANDSCAPE
    section.page_width = Inches(11.69)
    section.page_height = Inches(8.27)
    section.top_margin = Cm(1.45)
    section.bottom_margin = Cm(1.35)
    section.left_margin = Cm(1.55)
    section.right_margin = Cm(1.55)
    section.header_distance = Cm(0.55)
    section.footer_distance = Cm(0.55)

    styles = doc.styles
    normal = styles["Normal"]
    normal.font.name = "Aptos"
    normal.font.size = Pt(9.4)
    normal.font.color.rgb = TEXT
    normal.paragraph_format.space_after = Pt(4)
    normal.paragraph_format.line_spacing = 1.08

    title = styles["Title"]
    title.font.name = "Aptos Display"
    title.font.size = Pt(27)
    title.font.bold = True
    title.font.color.rgb = RGBColor(0, 0, 0)
    title.paragraph_format.space_after = Pt(8)
    title_ppr = title.element.get_or_add_pPr()
    for border in title_ppr.findall(qn("w:pBdr")):
        title_ppr.remove(border)

    for name, size, color, before, after in (
        ("Heading 1", 18, "000000", 14, 6),
        ("Heading 2", 13.5, "000000", 10, 4),
        ("Heading 3", 11, "000000", 8, 3),
    ):
        style = styles[name]
        style.font.name = "Aptos Display"
        style.font.size = Pt(size)
        style.font.bold = True
        style.font.color.rgb = RGBColor.from_string(color)
        style.paragraph_format.space_before = Pt(before)
        style.paragraph_format.space_after = Pt(after)
        style.paragraph_format.keep_with_next = True

    for style_name in ("List Bullet", "List Number"):
        style = styles[style_name]
        style.font.name = "Aptos"
        style.font.size = Pt(9.4)
        style.paragraph_format.space_after = Pt(1.2)
        style.paragraph_format.left_indent = Cm(0.55)
        style.paragraph_format.first_line_indent = Cm(-0.25)

    header = section.header.paragraphs[0]
    header.text = "TICA UI Test"
    header.alignment = WD_ALIGN_PARAGRAPH.LEFT
    for run in header.runs:
        run.font.name = "Aptos"
        run.font.size = Pt(8)
        run.font.bold = True
        run.font.color.rgb = RGBColor(0, 0, 0)
    add_page_number(section.footer.paragraphs[0])


def build():
    lines = SOURCE.read_text(encoding="utf-8").splitlines()
    doc = Document()
    configure(doc)

    i = 0
    first_title = True
    while i < len(lines):
        line = lines[i].rstrip()
        stripped = line.strip()
        if not stripped:
            i += 1
            continue
        if stripped.startswith("|"):
            rows, i = parse_table(lines, i)
            add_table(doc, rows)
            continue
        if stripped.startswith("# "):
            p = doc.add_paragraph(style="Title")
            add_inline(p, stripped[2:])
            if first_title:
                subtitle = doc.add_paragraph()
                subtitle.alignment = WD_ALIGN_PARAGRAPH.LEFT
                run = subtitle.add_run("Admin, organizer, and user dashboards: flows, navigation, and Laravel implementation map")
                run.font.name = "Aptos"
                run.font.size = Pt(12)
                run.font.color.rgb = MUTED
                subtitle.paragraph_format.space_after = Pt(14)
                first_title = False
            i += 1
            continue
        if stripped.startswith("## "):
            heading_text = stripped[3:]
            if heading_text not in (
                "Purpose and recommendation",
                "Product roles and ownership",
                "Definition of done",
            ):
                doc.add_page_break()
            p = doc.add_paragraph(style="Heading 1")
            add_inline(p, heading_text)
            i += 1
            continue
        if stripped.startswith("### "):
            p = doc.add_paragraph(style="Heading 2")
            if stripped[4:] in ("Status enums and transitions", "Authorization matrix"):
                p.paragraph_format.page_break_before = True
            add_inline(p, stripped[4:])
            i += 1
            continue
        if re.match(r"^\d+\. ", stripped):
            match = re.match(r"^(\d+\.)\s+(.*)", stripped)
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Cm(0.72)
            p.paragraph_format.first_line_indent = Cm(-0.55)
            p.paragraph_format.space_after = Pt(1.2)
            number_run = p.add_run(match.group(1) + " ")
            number_run.font.name = "Aptos"
            number_run.font.size = Pt(9.4)
            add_inline(p, match.group(2))
            i += 1
            continue
        if stripped.startswith("- "):
            p = doc.add_paragraph(style="List Bullet")
            add_inline(p, stripped[2:])
            i += 1
            continue

        paragraph_parts = [stripped]
        i += 1
        while i < len(lines):
            nxt = lines[i].strip()
            if not nxt or nxt.startswith(("#", "|", "- ")) or re.match(r"^\d+\. ", nxt):
                break
            paragraph_parts.append(nxt)
            i += 1
        p = doc.add_paragraph()
        add_inline(p, " ".join(paragraph_parts))

    props = doc.core_properties
    props.title = "TICA UI Test Dashboard Product Architecture"
    props.subject = "Admin organizer and user dashboard design and engineering blueprint"
    props.author = "TICA UI Test Product and Engineering"
    props.keywords = "TICA UI Test, dashboard, admin, organizer, user, Laravel, Inertia, React"
    doc.save(OUTPUT)
    print(OUTPUT)


if __name__ == "__main__":
    build()
