#!/usr/bin/env python3
"""Generate PWA icons from public/logo.png. Prefer the PHP script (GD)."""
import subprocess
from pathlib import Path

script = Path(__file__).with_name('make-pwa-icons.php')
subprocess.check_call(['php', str(script)])
