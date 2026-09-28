"""Baut app/index.html aus src/ruti.html und vendor/Sortable.min.js.

Aufruf:  python build.py
"""
from pathlib import Path

ROOT = Path(__file__).parent
src = (ROOT / "src" / "ruti.html").read_text(encoding="utf-8")
lib = (ROOT / "vendor" / "Sortable.min.js").read_text(encoding="utf-8")

marker = "<script>\n(function(){"
assert src.count(marker) == 1, "Start des App-Skripts nicht eindeutig gefunden"
body = src.replace(marker, "<script>\n" + lib + "\n</script>\n" + marker)

head = (
    '<!doctype html>\n<html lang="de">\n<head>\n<meta charset="utf-8">\n'
    '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">\n'
    '<meta name="theme-color" content="#EEF2F0">\n'
    "<style>html,body{margin:0}:root{padding-top:env(safe-area-inset-top,0px)}"
    "[hidden]{display:none!important}img{max-width:100%}</style>\n</head>\n<body>\n"
)
out = ROOT / "app" / "index.html"
out.write_text(head + body + "\n</body>\n</html>\n", encoding="utf-8")
print(f"geschrieben: {out}")
