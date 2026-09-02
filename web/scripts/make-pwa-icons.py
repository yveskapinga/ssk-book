import struct
import zlib
from pathlib import Path

ROOT = Path('/var/lib/.local-state/yves/workspace/ssk-book/web/public')
BLUE = (11, 74, 162)
WHITE = (255, 255, 255)
YELLOW = (255, 209, 0)


def chunk(tag: bytes, data: bytes) -> bytes:
    return struct.pack('>I', len(data)) + tag + data + struct.pack('>I', zlib.crc32(tag + data) & 0xFFFFFFFF)


def png(size: int, path: Path) -> None:
    rows = []
    for y in range(size):
        row = bytearray(b'\x00')
        for x in range(size):
            color = BLUE
            cx, cy = x / size, y / size
            if 0.22 < cx < 0.48 and 0.22 < cy < 0.78:
                color = WHITE
            elif 0.52 < cx < 0.78 and 0.22 < cy < 0.78:
                color = YELLOW
            row.extend(color)
        rows.append(bytes(row))
    ihdr = struct.pack('>IIBBBBB', size, size, 8, 2, 0, 0, 0)
    data = b''.join([
        b'\x89PNG\r\n\x1a\n',
        chunk(b'IHDR', ihdr),
        chunk(b'IDAT', zlib.compress(b''.join(rows), 9)),
        chunk(b'IEND', b''),
    ])
    path.write_bytes(data)


png(192, ROOT / 'icon-192.png')
png(512, ROOT / 'icon-512.png')
print('wrote icons')
