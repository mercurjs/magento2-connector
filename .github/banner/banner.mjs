// node .github/banner/banner.mjs > banner.svg && rsvg-convert -w 2640 -o .github/banner.png banner.svg
// Needs Alpha Lyrae installed for the renderer (github.com/vegaprotocol/alpha-lyrae); the committed artefact is the PNG.
// Hairline still frame: one stroke weight, isometric guide lines, two tiles,
// one title set in Inter like the docs. Palette from mercurjs.com.

const W = 1320
const H = 330

const C = {
  bg: "#090909",
  guide: "#363636",
  line: "#8a8a8a",
  edge: "#4e4e4e",
  plate: "#101010",
  hi: "#ffffff",
  mid: "#9ca3af",
  lo: "#7a7a7a",
}

const FONT = "Alpha Lyrae"
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

// Title in Alpha Lyrae the way raindrop.ai sets its display type:
// weight 500, contextual alternates off, tight tracking, 1.1 line height.
const T = `font-family="Alpha Lyrae" font-weight="500" fill="${C.hi}" style="font-feature-settings:'calt' 0"`
push(`<text x="80" y="166" font-size="76" letter-spacing="-3.4" ${T}>${esc("Mercur")}</text>`)
push(`<text x="80" y="250" font-size="76" letter-spacing="-3.4" ${T}>${esc("Magento 2 Connector")}</text>`)
push(`<text x="80" y="296" font-size="17" letter-spacing="-0.3" font-family="Alpha Lyrae" font-weight="500" fill="${C.mid}" style="font-feature-settings:'calt' 0">${esc("mercurjs.com")}</text>`)

push(`</svg>`)
process.stdout.write(out.join("\n") + "\n")
