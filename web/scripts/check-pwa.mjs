import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const manifest = JSON.parse(readFileSync(resolve('dist/manifest.webmanifest'), 'utf8'))
const sizes = new Set((manifest.icons ?? []).map((icon) => icon.sizes))
if (!sizes.has('192x192') || !sizes.has('512x512')) {
  console.error('PWA manifest must include 192x192 and 512x512 icons.')
  process.exit(1)
}
if (manifest.display !== 'standalone') {
  console.error('PWA manifest must be installable (display=standalone).')
  process.exit(1)
}
console.log('PWA manifest OK')
