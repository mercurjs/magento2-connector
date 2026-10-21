// node .github/banner/banner.mjs > .github/banner.svg
// A still frame in the hairline idiom: one stroke weight, one monospace face,
// square corners, colour only where it means something. Palette from mercurjs.com.

const W = 1320
const H = 330

const C = {
  bg: "#090909",
  plate: "#111111",
  line: "#262626",
  rule: "#1c1c1c",
  tile: "#222222",
  hi: "#ffffff",
  text: "#d4d4d4",
  mid: "#9ca3af",
  lo: "#4a4a4a",
  accent: "#4c24dd",
  green: "#17803d",
}

const FONT = "JetBrains Mono, SF Mono, SFMono-Regular, Menlo, Consolas, Liberation Mono, monospace"
const esc = (s) => String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/"/g, "&quot;")
const measure = (s, size) => s.length * size * 0.6

const out = []
const push = (s) => out.push(s)

const text = (x, y, str, { size = 15, fill = C.text, weight = 400, anchor = "start", spacing = 0 } = {}) =>
  push(`<text x="${x}" y="${y}" font-size="${size}" fill="${fill}" font-weight="${weight}" text-anchor="${anchor}"${spacing ? ` letter-spacing="${spacing}"` : ""}>${esc(str)}</text>`)
const rect = (x, y, w, h, { fill = "none", stroke, dash } = {}) =>
  push(`<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${fill}"${stroke ? ` stroke="${stroke}" stroke-width="1"` : ""}${dash ? ` stroke-dasharray="${dash}"` : ""} shape-rendering="crispEdges"/>`)
const line = (x1, y1, x2, y2, stroke = C.line) =>
  push(`<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${stroke}" stroke-width="1" shape-rendering="crispEdges"/>`)
const marker = (x, mid, fill) => rect(x, mid - 5, 10, 10, { fill })

// Deterministic tiles, denser toward the edges, kept out of the title and panels.
const seed = (n) => () => ((n = (n * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff)
const rnd = seed(11)
const CLEAR = [
  { x: 60, y: 40, w: 690, h: 250 },
  { x: 800, y: 24, w: 460, h: 290 },
]
const tiles = () => {
  const size = 88
  for (let gx = -size / 2; gx < W; gx += size) {
    for (let gy = -size / 2 - 20; gy < H; gy += size) {
      const cx = gx + size / 2
      const cy = gy + size / 2
      const edge = Math.max(Math.abs(cx - W / 2) / (W / 2), Math.abs(cy - H / 2) / (H / 2))
      const blocked = CLEAR.some((c) => cx > c.x - size / 2 && cx < c.x + c.w + size / 2 && cy > c.y - size / 2 && cy < c.y + c.h + size / 2)
      const r = rnd()
      if (blocked || r > 0.12 + edge * 0.3) {
        continue
      }
      if (r < 0.08) {
        rect(gx, gy, size, size, { stroke: C.tile, dash: "2 4" })
      } else {
        rect(gx, gy, size, size, { stroke: C.tile })
      }
    }
  }
  // crosses on the grid, like survey marks
  for (let gx = -size / 2; gx <= W + size; gx += size) {
    for (let gy = -size / 2 - 20; gy <= H + size; gy += size) {
      if (rnd() > 0.55) continue
      line(gx - 4, gy, gx + 5, gy, C.lo)
      line(gx, gy - 4, gx, gy + 5, C.lo)
    }
  }
}

// A small plate with a header and rows, the product doing the thing.
const panel = (x, y, w, h, draw) => {
  push(`<rect x="${x + 6}" y="${y + 6}" width="${w}" height="${h}" fill="#000000" opacity="0.6"/>`)
  rect(x, y, w, h, { fill: C.plate, stroke: C.line })
  push(`<g transform="translate(${x} ${y})">`)
  draw()
  push(`</g>`)
}

const mark = (x, y, scale, fill) =>
  push(
    `<g transform="translate(${x} ${y}) scale(${scale})" fill="${fill}">` +
      `<path d="M7 4V14.988L16.541 9.49398L7 4Z"/>` +
      `<path d="M29.5993 28.0002V17.0122L20.0583 22.5062L29.5993 28.0002Z"/>` +
      `<path d="M7 17.012V28L29.5994 14.988V4L7 17.012Z"/>` +
      `</g>`
  )

push(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" font-family="${FONT}" role="img" aria-label="Mercur Magento 2 Connector: marketplace offers inside your Magento store.">`)
rect(0, 0, W, H, { fill: C.bg })
tiles()

// Left: title block, Nitro's placement.
text(80, 128, "Mercur", { size: 64, fill: C.hi, weight: 500 })
text(80, 196, "Magento 2 Connector", { size: 64, fill: C.hi, weight: 500 })
text(80, 246, "Marketplace offers, inside the Magento store you already run.", { size: 18, fill: C.mid })
text(80, 276, "mercurjs.com", { size: 16, fill: C.lo })

// Right: two plates back to front. Behind, the Magento product page listing
// seller offers. In front, the Mercur order group the checkout turned into.
panel(860, 36, 360, 170, () => {
  marker(20, 28, C.lo)
  text(40, 33, "PRODUCT · MAG-SKU-2041", { size: 13, fill: C.mid, spacing: 1.2 })
  line(0, 56, 360, 56)
  const rows = [
    ["Sold by us", "$ 129.00"],
    ["Northwind Audio", "$ 119.00"],
    ["Keller & Co", "$ 124.50"],
  ]
  rows.forEach(([seller, price], i) => {
    const y = 56 + i * 38
    text(20, y + 24, seller, { size: 15, fill: C.text })
    text(340, y + 24, price, { size: 15, fill: C.mid, anchor: "end" })
    if (i < rows.length - 1) line(0, y + 38, 360, y + 38, C.rule)
  })
})

panel(940, 128, 320, 172, () => {
  marker(20, 28, C.accent)
  text(40, 33, "ORDER GROUP #G1042", { size: 13, fill: C.mid, spacing: 1.2 })
  line(0, 56, 320, 56)
  const rows = [
    ["Northwind Audio", "#1042-1", C.green, "SHIPPED"],
    ["Keller & Co", "#1042-2", C.lo, "OPEN"],
  ]
  rows.forEach(([seller, id, tone, status], i) => {
    const y = 56 + i * 44
    text(20, y + 27, seller, { size: 15, fill: C.text })
    text(160, y + 27, id, { size: 14, fill: C.lo })
    marker(240, y + 22, tone)
    text(256, y + 27, status, { size: 12, fill: C.mid, spacing: 1 })
    if (i < rows.length - 1) line(0, y + 44, 320, y + 44, C.rule)
  })
  line(0, 144, 320, 144)
  text(20, 164, "1 payment · 2 seller orders", { size: 13, fill: C.lo })
})

// Mercur mark, top right, the way Nitro places its logo.
mark(1226, 20, 1.9, C.hi)

push(`</svg>`)
process.stdout.write(out.join("\n") + "\n")
