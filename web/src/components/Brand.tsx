import { Link } from 'react-router-dom'

export function Brand({ to, className = 'brand' }: { to: string; className?: string }) {
  return (
    <Link className={className} to={to}>
      <img src="/logo.png" width={40} height={40} alt="" />
      <span>SSK <strong>Book</strong></span>
    </Link>
  )
}
