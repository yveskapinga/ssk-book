import { Link } from 'react-router-dom'

export function Brand({ to, className = 'brand', onClick }: { to: string; className?: string; onClick?: () => void }) {
  return (
    <Link className={className} to={to} onClick={onClick}>
      <img src="/logo.png" width={40} height={40} alt="" />
      <span>SSK <strong>Book</strong></span>
    </Link>
  )
}
