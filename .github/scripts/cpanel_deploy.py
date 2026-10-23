#!/usr/bin/env python3
"""Upload only IMGTR application files using cPanel's HTTPS API (no FTP/SSH).

Requires CPANEL_HOST, CPANEL_USERNAME, CPANEL_API_TOKEN, CPANEL_DEPLOY_DIR.
DRY_RUN=1 (default) only lists intended changes; does not write remote files.
"""
from __future__ import annotations

import json
import os
import re
import ssl
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

ROOT = Path.cwd()
EXCLUDED_FILES = {
    ".htaccess", ".gitignore", ".env", "README.md", "DEPLOYMENT.md",
    "includes/config.php", "includes/config.example.php",
}
EXCLUDED_PREFIXES = (
    ".git/", ".github/", "downloads/", "uploads/", "logs/", "cache/",
    ".well-known/", "tmp/", "temp/", "storage/", "sessions/",
    "cgi-bin/", "updates/", "node_modules/",
)
EXCLUDED_SUFFIXES = (".sql", ".sql.gz", ".sqlite", ".sqlite3", ".log")
REQUIRED_REMOTE = {"index.php", "includes", "assets", ".htaccess"}
CTX = ssl.create_default_context()


def safe_path(path: str) -> bool:
    path = path.replace("\\", "/").lstrip("/")
    parts = path.split("/")
    if not path or any(part in ("", ".", "..") for part in parts):
        return False
    if path in EXCLUDED_FILES or any(path.startswith(p) for p in EXCLUDED_PREFIXES):
        return False
    if any(part.startswith(".env") or part == "node_modules" for part in parts):
        return False
    if path.endswith(EXCLUDED_SUFFIXES) or Path(path).name == "error_log":
        return False
    return True


def get_git_paths() -> list[str]:
    raw = subprocess.check_output(["git", "ls-files", "-z"])
    return sorted(
        p for p in raw.decode("utf-8").split("\0")
        if p and safe_path(p) and (ROOT / p).is_file()
    )


def choose_paths() -> list[str]:
    all_paths = get_git_paths()
    before = os.environ.get("GIT_BASE_SHA", "").strip()
    if not before or before == "0" * 40:
        return all_paths
    try:
        subprocess.run(["git", "cat-file", "-e", before + "^{commit}"], check=True, capture_output=True)
        changed = subprocess.check_output(
            ["git", "diff", "--name-only", "--diff-filter=ACMR", "-z", before, "HEAD"]
        ).decode("utf-8").split("\0")
        return sorted(set(all_paths).intersection(changed))
    except subprocess.CalledProcessError:
        print("Base commit unavailable: falling back to full source sync.")
        return all_paths


def request(path: str, *, fields: dict[str, str] | None = None, upload: tuple[str, bytes] | None = None) -> dict:
    host = os.environ["CPANEL_HOST"].strip().removeprefix("https://").rstrip("/")
    if not host or "/" in host or ":" in host or not re.fullmatch(r"[A-Za-z0-9.-]+", host):
        raise RuntimeError("CPANEL_HOST must be a hostname without scheme or port")
    url = "https://" + host + ":2083" + path
    username = os.environ["CPANEL_USERNAME"].strip()
    token = os.environ["CPANEL_API_TOKEN"].strip()
    headers = {"Authorization": "cpanel " + username + ":" + token,
               "Accept": "application/json"}
    if not username or not token:
        raise RuntimeError("Missing cPanel API credentials")
    if upload is not None:
        filename, payload = upload
        boundary = "imgtr-" + uuid.uuid4().hex
        data = (
            ("--" + boundary + "\r\n"
             'Content-Disposition: form-data; name="dir"\r\n\r\n'
             + fields["dir"] + "\r\n"
             + "--" + boundary + "\r\n"
             'Content-Disposition: form-data; name="overwrite"\r\n\r\n1\r\n'
             + "--" + boundary + "\r\n"
             'Content-Disposition: form-data; name="file-1"; filename="' + filename + '"\r\n'
             'Content-Type: application/octet-stream\r\n\r\n').encode("utf-8")
            + payload + ("\r\n--" + boundary + "--\r\n").encode("ascii")
        )
        headers["Content-Type"] = "multipart/form-data; boundary=" + boundary
        req = urllib.request.Request(url, data=data, headers=headers, method="POST")
    elif fields is not None:
        req = urllib.request.Request(url, data=urllib.parse.urlencode(fields).encode(), headers=headers, method="POST")
    else:
        req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=55, context=CTX) as res:
            obj = json.load(res)
    except urllib.error.HTTPError as exc:
        raise RuntimeError("cPanel HTTP " + str(exc.code) + " (" + path.split("?")[0] + ")") from None
    except (urllib.error.URLError, TimeoutError) as exc:
        raise RuntimeError("cPanel connection failed (" + type(exc).__name__ + ")") from None
    if not isinstance(obj, dict):
        raise RuntimeError("Unexpected cPanel API response")
    if "cpanelresult" in obj:
        result = obj["cpanelresult"]
        if int(result.get("event", {}).get("result", 0)) != 1:
            raise RuntimeError("cPanel API2 operation failed: " + str(result.get("error") or result.get("data", []))[:200])
        return obj
    if int(obj.get("status", 0)) != 1 or obj.get("errors"):
        details = obj.get("data")
        raise RuntimeError(
            "cPanel UAPI operation failed: "
            + str(obj.get("errors") or obj.get("messages"))[:200]
            + " | file details: " + json.dumps(details, ensure_ascii=False, default=str)[:500]
        )
    return obj


def listing(directory: str) -> dict[str, dict]:
    query = urllib.parse.urlencode({"dir": directory, "show_hidden": "1", "types": "file|dir"})
    result = request("/execute/Fileman/list_files?" + query)
    data = result.get("data")
    if not isinstance(data, list):
        raise RuntimeError("Fileman/list_files did not return a list")
    return {str(item.get("file") or item.get("name")): item for item in data if isinstance(item, dict)}


def main() -> None:
    docroot = os.environ.get("CPANEL_DEPLOY_DIR", "").strip().strip("/")
    if not docroot or not re.fullmatch(r"[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*", docroot):
        raise RuntimeError("Set CPANEL_DEPLOY_DIR to an existing website root, e.g. public_html or imgtr.xyz")
    parts = docroot.split("/")
    if any(part in (".", "..") for part in parts):
        raise RuntimeError("Unsafe website root")
    dry_run = os.environ.get("DRY_RUN", "1") == "1"
    print("Mode:", "READ-ONLY PREVIEW" if dry_run else "LIVE UPLOAD")
    print("Document root:", docroot)

    root_items = listing(docroot)
    missing = REQUIRED_REMOTE.difference(root_items)
    if missing:
        raise RuntimeError("Wrong or incomplete document root: missing " + ", ".join(sorted(missing)))
    if not root_items.get("includes", {}).get("type", "dir") == "dir":
        raise RuntimeError("Document root has no includes directory")
    includes = listing(docroot + "/includes")
    if "config.php" not in includes:
        raise RuntimeError("Production config.php not found: refusing deploy")

    changed = choose_paths()
    print("Source files selected:", len(changed))
    if not changed:
        print("No source changes to upload.")
        return

    directory_cache: dict[str, dict[str, dict]] = {docroot: root_items, docroot + "/includes": includes}
    for i, relative in enumerate(changed, 1):
        print(f"[{i}/{len(changed)}] {relative}")
        if dry_run:
            continue
        segments = relative.split("/")
        parent = docroot
        for child in segments[:-1]:
            if parent not in directory_cache:
                directory_cache[parent] = listing(parent)
            entries = directory_cache[parent]
            if child not in entries:
                # API 2 is used only to create missing directories; UAPI has no mkdir.
                params = urllib.parse.urlencode({
                    "cpanel_jsonapi_user": os.environ["CPANEL_USERNAME"],
                    "cpanel_jsonapi_apiversion": "2",
                    "cpanel_jsonapi_module": "Fileman",
                    "cpanel_jsonapi_func": "mkdir",
                })
                request("/json-api/cpanel?" + params, fields={"path": parent, "name": child, "permissions": "0755"})
                entries[child] = {"file": child, "type": "dir"}
            elif entries[child].get("type", "dir") != "dir":
                raise RuntimeError("Expected directory, found file: " + parent + "/" + child)
            parent += "/" + child
        request("/execute/Fileman/upload_files", fields={"dir": parent},
                upload=(segments[-1], (ROOT / relative).read_bytes()))
    print("cPanel API deploy finished:", len(changed), "files. No files deleted.")


if __name__ == "__main__":
    try:
        main()
    except Exception as err:
        print("ERROR:", err, file=sys.stderr)
        sys.exit(1)
