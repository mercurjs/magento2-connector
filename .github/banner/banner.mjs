// node .github/banner/banner.mjs > banner.svg && rsvg-convert -w 2640 -o .github/banner.png banner.svg
// Needs Inter installed for the renderer; the committed artefact is the PNG.
// Hairline still frame: one stroke weight, isometric guide lines, two tiles,
// one title set in Inter like the docs. Palette from mercurjs.com.

const W = 1320
const H = 330

const C = {
  bg: "#090909",
  guide: "#1b1b1b",
  line: "#3a3a3a",
  edge: "#262626",
  plate: "#0e0e0e",
  hi: "#ffffff",
  mid: "#9ca3af",
  lo: "#5a5a5a",
}

const FONT = "Inter"
const out = []
const push = (s) => out.push(s)
const esc = (s) => String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;")

const line = (x1, y1, x2, y2, stroke = C.line) =>
  push(`<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${stroke}" stroke-width="1"/>`)

// Isometric projection: unit x runs down-right, unit y runs down-left.
const ISO = "matrix(0.866 0.5 -0.866 0.5 0 0)"
const iso = (x, y) => [(x - y) * 0.866, (x + y) * 0.5]

// Sparse guide lines along both isometric axes, the reference's background.
const guides = () => {
  const dx = Math.cos(Math.PI / 6) * 3000
  const dy = Math.sin(Math.PI / 6) * 3000
  for (const x of [-1500, -900, -300, 300, 900]) {
    line(x, -600, x + dx, -600 + dy, C.guide)
  }
  for (const x of [600, 1200, 1800, 2400, 3000]) {
    line(x, -600, x - dx, -600 + dy, C.guide)
  }
}

// A rounded plate in isometric view with a thin extruded edge below it.
const tile = (cx, cy, size, depth, icon) => {
  const r = size * 0.18
  const plate = `<rect x="${-size / 2}" y="${-size / 2}" width="${size}" height="${size}" rx="${r}" ry="${r}"`
  const at = (dy) => `translate(${cx} ${cy + dy}) ${ISO}`
  // bottom outline
  push(`<g transform="${at(depth)}">${plate} fill="${C.plate}" stroke="${C.edge}" stroke-width="1"/></g>`)
  // side edges at the extreme corners
  const [lx, ly] = iso(-size / 2 + r * 0.3, size / 2 - r * 0.3)
  const [rx, ry] = iso(size / 2 - r * 0.3, -size / 2 + r * 0.3)
  const [bx, by] = iso(size / 2 - r * 0.3, size / 2 - r * 0.3)
  line(cx + lx, cy + ly, cx + lx, cy + ly + depth, C.edge)
  line(cx + rx, cy + ry, cx + rx, cy + ry + depth, C.edge)
  line(cx + bx, cy + by, cx + bx, cy + by + depth, C.edge)
  // top plate
  push(`<g transform="${at(0)}">${plate} fill="${C.plate}" stroke="${C.line}" stroke-width="1"/>` +
    `${plate.replace(`x="${-size / 2}" y="${-size / 2}" width="${size}" height="${size}"`, `x="${-size / 2 + 10}" y="${-size / 2 + 10}" width="${size - 20}" height="${size - 20}"`)} fill="none" stroke="${C.edge}" stroke-width="1"/>` +
    `<g transform="translate(${-size / 2}) translate(0 ${-size / 2})">${icon(size)}</g>` +
    `</g>`)
}

// The Mercur mark, outlined, centred in a tile of `size`.
const mercurMark = (size) => {
  const s = (size * 0.46) / 32
  const o = (size - 32 * s) / 2
  return `<g transform="translate(${o} ${o}) scale(${s})" fill="${C.plate}" stroke="${C.hi}" stroke-width="${1 / s}" stroke-linejoin="round">` +
    `<path d="M7 4V14.988L16.541 9.49398L7 4Z"/>` +
    `<path d="M29.5993 28.0002V17.0122L20.0583 22.5062L29.5993 28.0002Z"/>` +
    `<path d="M7 17.012V28L29.5994 14.988V4L7 17.012Z"/>` +
    `</g>`
}

// A shopping bag, outlined, for the storefront side.
const bagMark = (size) => {
  const s = size * 0.44
  const o = (size - s) / 2
  const u = s / 24
  return `<g transform="translate(${o} ${o}) scale(${u})" fill="${C.plate}" stroke="${C.hi}" stroke-width="${1 / u}" stroke-linejoin="round" stroke-linecap="round">` +
    `<path d="M4 8H20L19 21H5L4 8Z"/>` +
    `<path d="M8.5 8V6.5C8.5 4.5 10 3 12 3C14 3 15.5 4.5 15.5 6.5V8"/>` +
    `</g>`
}

push(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" font-family="${FONT}" role="img" aria-label="Mercur Magento 2 Connector">`)
push(`<rect width="${W}" height="${H}" fill="${C.bg}"/>`)
guides()

// Two tiles, back and front, to the right, like the reference.
tile(930, 96, 150, 10, mercurMark)
tile(1120, 206, 150, 10, bagMark)

// One small cross, the reference's survey mark.
line(792, 170, 806, 170, C.lo)
line(799, 163, 799, 177, C.lo)

// Title, Inter, weight 600, tight tracking like the docs h1.
push(`<text x="80" y="108" font-size="20" fill="${C.mid}" font-weight="400" letter-spacing="-0.2">${esc("mercurjs.com")}</text>`)
push(`<text x="80" y="188" font-family="Inter SemiBold" font-size="68" fill="${C.hi}" font-weight="600" letter-spacing="-2">${esc("Mercur")}</text>`)
push(`<text x="80" y="266" font-family="Inter SemiBold" font-size="68" fill="${C.hi}" font-weight="600" letter-spacing="-2">${esc("Magento 2 Connector")}</text>`)

push(`</svg>`)
process.stdout.write(out.join("\n") + "\n")
