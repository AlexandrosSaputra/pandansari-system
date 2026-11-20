import { encodeCode128B } from './code128'

export function Code128Barcode({ value }: { value: string }) {
  const barcode = encodeCode128B(value)

  return (
    <svg
      className="pos-barcode-svg"
      viewBox={`0 0 ${barcode.width} 64`}
      role="img"
      aria-label={`Barcode konfirmasi pembayaran ${value}`}
      preserveAspectRatio="xMidYMid meet"
      shapeRendering="crispEdges"
    >
      <rect width={barcode.width} height="64" fill="white" />
      {barcode.bars.map((bar, index) => (
        <rect
          key={`${bar.x}-${index}`}
          x={bar.x}
          y="0"
          width={bar.width}
          height="64"
          fill="currentColor"
        />
      ))}
    </svg>
  )
}
