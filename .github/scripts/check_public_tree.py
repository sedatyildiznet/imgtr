#!/usr/bin/env python3
"""Block accidental publication of private runtime files and common credential patterns."""
from __future__ import annotations
import pathlib
import re
import subprocess
import sys

FORBIDDEN = (
    re.compile(r"(^|/)(?:\.env(?:\..*)?|config\.php|id_rsa|id_ed25519|\.htpasswd)$", re.I),
    re.compile(r"(^|/)(?:uploads|logs|cache|storage|sessions|\.well-known)(/|$)", re.I),
    re.compile(r"\.(?:pem|key|p12|pfx|bak|backup|sql\.gz|sqlite3?|log)$", re.I),
)
# SQL migrations within updates/ are intentionally versioned.
PRIVATE_CONFIG = {"includes/config.php", "config.php"}
PATTERNS = (
    ("private key", re.compile(r"-----BEGIN (?:[A-Z ]*PRIVATE|OPENSSH PRIVATE) KEY-----")),
    ("GitHub token", re.compile(r"\b(?:gh[pousr]_[A-Za-z0-9_]{15,}|github_pat_[A-Za-z0-9_]{15,})\b")),
    ("cloud service key", re.compile(r"\b(?:AKIA[0-9A-Z]{16}|AIza[0-9A-Za-z_-]{25,}|sk_live_[A-Za-z0-9]{12,})\b")),
    ("hardcoded DB password", re.compile(
        r"""define\s*\(\s*['"]DB_PASS['"]\s*,\s*['"](?!YOUR_|EXAMPLE|REPLACE|CHANGE_ME)[^'"]{8,}['"]""",
        re.I,
    )),
)
REVIEW_EXTS = {".php", ".js", ".json", ".yaml", ".yml", ".md", ".html", ".txt", ".sh", ".py", ".css", ".sql"}
raw = subprocess.check_output(["git", "ls-files", "-s", "-z"])
issues = []
for record in filter(None, raw.decode("utf-8").split("\0")):
    header, name = record.split("\t", 1)
    mode = header.split(" ", 1)[0]
    if mode == "120000":
        issues.append((name, "tracked symlink"))
        continue
    if name in PRIVATE_CONFIG or any(pattern.search(name) for pattern in FORBIDDEN):
        if not (name.startswith("updates/") and name.endswith(".sql")):
            issues.append((name, "production-only file"))
        continue
    path = pathlib.Path(name)
    if path.suffix.lower() not in REVIEW_EXTS and path.name not in {".htaccess", ".gitignore"}:
        continue
    if not path.is_file() or path.stat().st_size > 2_000_000:
        continue
    content = path.read_text(encoding="utf-8", errors="replace")
    for title, pattern in PATTERNS:
        if pattern.search(content):
            issues.append((name, title))
if issues:
    print("Source safety validation failed:")
    for filename, reason in issues:
        print(" -", filename, ":", reason)
    sys.exit(1)
print("Source safety validation passed.")
