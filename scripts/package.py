#!/usr/bin/env python3
"""Create two clean Moodle plugin ZIPs from this repository."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / 'dist'
DIST.mkdir(exist_ok=True)
for folder, filename in [
    ('questionnaireanalytics', 'report_questionnaireanalytics-0.1.1-beta.zip'),
    ('surveypulse', 'mod_surveypulse-0.1.0-beta.zip'),
]:
    with ZipFile(DIST / filename, 'w', ZIP_DEFLATED) as archive:
        for path in sorted((ROOT / folder).rglob('*')):
            if path.is_file() and not any(part in {'.git', '__pycache__', '.DS_Store'} for part in path.parts):
                archive.write(path, path.relative_to(ROOT).as_posix())
    print(DIST / filename)
