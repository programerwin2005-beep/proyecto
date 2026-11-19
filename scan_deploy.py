import os, io, re
pat = re.compile(r"(oracle|oci|ubuntu|apache|nginx|https?://[a-z0-9\.\-]+|dominio|deploy|producci[oó]n|cloud)", re.I)
hits = []
for root, dirs, files in os.walk("."):
    dirs[:] = [d for d in dirs if d not in (".git", "vendor", "node_modules", "storage", "public", "bootstrap")]
    for fn in files:
        if fn.lower().endswith((".md", ".env", ".example", ".yml", ".yaml", ".sh", ".conf", ".txt")) or fn.startswith(".env"):
            p = os.path.join(root, fn)
            try:
                s = io.open(p, encoding="utf-8", errors="ignore").read()
            except Exception:
                continue
            for m in pat.finditer(s):
                line = s[max(0, m.start()-60):m.start()+90].replace("\n", " ")
                hits.append((p, line.strip()[:140]))
seen = set()
for p, l in hits:
    k = (p, l[:60])
    if k in seen:
        continue
    seen.add(k)
    print(p, "|", l)
    if len(seen) > 40:
        break
