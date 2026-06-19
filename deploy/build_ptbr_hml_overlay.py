from __future__ import annotations

import argparse
import difflib
import json
import shutil
import unicodedata
from pathlib import Path


def canonical(value: str) -> str:
    normalized = unicodedata.normalize("NFD", value)
    return "".join(char for char in normalized if unicodedata.category(char) != "Mn")


def merge_file(remote_file: Path, local_file: Path, output_file: Path) -> dict:
    remote_text = remote_file.read_text(encoding="utf-8")
    local_text = local_file.read_text(encoding="utf-8")
    remote_lines = remote_text.splitlines(keepends=True)
    local_lines = local_text.splitlines(keepends=True)
    result = list(remote_lines)
    applied = 0
    skipped = []

    matcher = difflib.SequenceMatcher(a=remote_lines, b=local_lines, autojunk=False)
    offset = 0
    for tag, i1, i2, j1, j2 in matcher.get_opcodes():
        if tag != "replace":
            continue

        remote_block = remote_lines[i1:i2]
        local_block = local_lines[j1:j2]
        if len(remote_block) != len(local_block):
            skipped.append({"remote": [i1 + 1, i2], "local": [j1 + 1, j2]})
            continue

        safe = all(
            canonical(remote_line) == canonical(local_line)
            for remote_line, local_line in zip(remote_block, local_block)
        )
        if not safe:
            skipped.append({"remote": [i1 + 1, i2], "local": [j1 + 1, j2]})
            continue

        start = i1 + offset
        end = i2 + offset
        result[start:end] = local_block
        offset += len(local_block) - len(remote_block)
        applied += len(local_block)

    output_file.parent.mkdir(parents=True, exist_ok=True)
    output_file.write_text("".join(result), encoding="utf-8", newline="")
    return {"applied_lines": applied, "skipped_blocks": skipped}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--remote", required=True)
    parser.add_argument("--local", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--report", required=True)
    args = parser.parse_args()

    remote_root = Path(args.remote).resolve()
    local_root = Path(args.local).resolve()
    output_root = Path(args.output).resolve()
    report_path = Path(args.report).resolve()

    if output_root.exists():
        shutil.rmtree(output_root)
    output_root.mkdir(parents=True)

    report = {}
    allowed_suffixes = {".php", ".css", ".js", ".json", ".md"}
    for remote_file in remote_root.rglob("*"):
        if not remote_file.is_file() or remote_file.suffix.lower() not in allowed_suffixes:
            continue

        relative = remote_file.relative_to(remote_root)
        local_file = local_root / relative
        if not local_file.is_file():
            continue

        output_file = output_root / relative
        result = merge_file(remote_file, local_file, output_file)
        if result["applied_lines"] > 0:
            report[str(relative).replace("\\", "/")] = result
        elif output_file.exists():
            output_file.unlink()

    for directory in sorted(output_root.rglob("*"), reverse=True):
        if directory.is_dir() and not any(directory.iterdir()):
            directory.rmdir()

    report_path.write_text(
        json.dumps(report, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    print(f"files={len(report)}")
    print(f"lines={sum(item['applied_lines'] for item in report.values())}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
