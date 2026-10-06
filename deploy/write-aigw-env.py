#!/usr/bin/env python3
from pathlib import Path

vals: dict[str, str] = {}
for line in Path("/opt/ssk-book/.env.prod").read_text().splitlines():
    if not line.strip() or line.strip().startswith("#") or "=" not in line:
        continue
    key, value = line.split("=", 1)
    vals[key.strip()] = value.strip()

out = Path("/opt/ai-gateway/.env")
out.write_text(
    "AIGW_API_KEY={}\n"
    "GEMINI_API_KEY={}\n"
    "DEEPSEEK_API_KEY={}\n"
    "DEEPSEEK_GENERATION_MODEL={}\n"
    "OLLAMA_BASE_URL=http://ollama:11434\n".format(
        vals.get("AIGW_API_KEY", "dev_only_change_me"),
        vals.get("GEMINI_API_KEY", ""),
        vals.get("DEEPSEEK_API_KEY", ""),
        vals.get("DEEPSEEK_GENERATION_MODEL", "deepseek-flash"),
    )
)
out.chmod(0o600)
print("wrote", out)
