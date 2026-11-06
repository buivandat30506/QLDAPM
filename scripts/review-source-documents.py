"""Read DOCX XML with the Python standard library; preserve originals and master prompt.

Run --write to refresh reviewed source registers; default checks generated data.
Images are inventoried separately: text extraction is not diagram verification.
"""
from pathlib import Path
import hashlib
import json
import re
import sys
import xml.etree.ElementTree as ET
import zipfile

ROOT = Path(__file__).resolve().parent.parent
NS = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
FIELDS = ["Use Case ID", "Use Case Name", "Use Case Description", "Actor", "Priority", "Trigger", "Pre-Condition",
          "Post-Condition", "Basic Flow", "Alternative Flow", "Exception Flow", "Business Rule", "Non-Functional"]


def read_docx(path):
    with zipfile.ZipFile(path) as archive:
        paragraphs = []
        for part in ["word/document.xml", "word/footnotes.xml", "word/endnotes.xml"]:
            if part not in archive.namelist():
                continue
            tree = ET.fromstring(archive.read(part))
            for paragraph in tree.findall(".//w:p", NS):
                value = "".join((node.text or "") if node.tag == "{" + NS["w"] + "}t" else "\n"
                                for node in paragraph.iter() if node.tag in ["{" + NS["w"] + "}t", "{" + NS["w"] + "}br", "{" + NS["w"] + "}tab"])
                if value.strip():
                    paragraphs.append(value.strip())
        return paragraphs, len([name for name in archive.namelist() if name.startswith("word/media/")])


def normalize(value):
    return re.sub(r"\s+", " ", value).strip()


register = []
texts = {}
for path in sorted((ROOT / "docs/source").glob("*.docx")):
    paragraphs, images = read_docx(path)
    texts[path.name] = paragraphs
    register.append({"source": path.relative_to(ROOT).as_posix(), "sha256": hashlib.sha256(path.read_bytes()).hexdigest(),
                     "nonempty_paragraphs": len(paragraphs), "embedded_media": images,
                     "review": "Text inventoried; implementation review is tracked by module in implementation-progress.md"})
if not register:
    raise RuntimeError("Missing docs/source/*.docx")
uc_names = [name for name in texts if re.fullmatch(r"UseCase_va_DacTa(?: \(\d+\))?\.docx", name)]
if len(uc_names) != 1:
    raise RuntimeError("Expected exactly one original UseCase_va_DacTa DOCX")
source_ucs = {}
current = None
field = None
for line in texts[uc_names[0]]:
    heading = re.match(r"^(UC-\d{2}) - ", line)
    if heading:
        current = heading[1]
        source_ucs[current] = {}
        field = None
    elif current and line in FIELDS:
        field = line
        source_ucs[current][field] = []
    elif current and field and not re.match(r"^M\d{2} - ", line):
        source_ucs[current][field].append(line)
if set(source_ucs) != {f"UC-{number:02d}" for number in range(1, 49)}:
    raise RuntimeError("Original DOCX must contain all 48 UC")
master_path = ROOT / "CODEX_MASTER_PROMPT_QL_HANG_KHONG.md"
baseline = json.loads((ROOT / "docs/requirements-source.json").read_text(encoding="utf-8"))
if baseline["source_sha256"] != hashlib.sha256(master_path.read_bytes()).hexdigest():
    raise RuntimeError("Master prompt changed: review the baseline first")
overrides = {}
for uc in baseline["use_cases"]:
    original = {key: normalize(" ".join(values)) for key, values in source_ucs[uc["uc_id"]].items()}
    differences = {key: value for key, value in original.items() if value != normalize(uc["attributes"].get(key, ""))}
    # Only the explicitly identified omitted flow cells may augment the current master baseline.
    if any(key not in ["Alternative Flow", "Exception Flow"] or normalize(uc["attributes"].get(key, "")) for key in differences):
        raise RuntimeError("New nonempty source disagreement requires review: " + uc["uc_id"])
    if differences:
        overrides[uc["uc_id"]] = differences
ba = texts["Business_Analysis_QL_Hang_Khong.docx"]
rules = {line: ba[index + 1] for index, line in enumerate(ba[:-1]) if re.fullmatch(r"BR-\d{3}", line)}
nfr = {line: {"name": ba[index + 1], "requirement": ba[index + 2], "verification": ba[index + 3]}
       for index, line in enumerate(ba[:-3]) if re.fullmatch(r"NFR-\d{3}", line)}
if len(rules) != 25 or len(nfr) != 18:
    raise RuntimeError("Business Analysis definitions incomplete")
supplement = {"master_sha256": baseline["source_sha256"], "use_case_source": "docs/source/" + uc_names[0],
              "business_analysis_source": "docs/source/Business_Analysis_QL_Hang_Khong.docx",
              "use_case_count": len(source_ucs), "flow_overrides": overrides, "business_rules": rules, "nonfunctional_requirements": nfr}
for filename, value in [("source-register.json", register), ("requirements-docx-supplement.json", supplement)]:
    path = ROOT / "docs" / filename
    content = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if "--write" in sys.argv:
        path.write_text(content, encoding="utf-8")
    elif not path.is_file() or json.loads(path.read_text(encoding="utf-8")) != value:
        raise RuntimeError("Source register differs; review before running --write: " + filename)
print(f"Sources checked: {len(register)} DOCX, 48 UC, {len(overrides)} omitted flow cells, 25 BR, 18 NFR. No original/master files changed.")
