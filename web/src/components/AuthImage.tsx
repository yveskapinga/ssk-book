import { useEffect, useState } from 'react'
import { ApiError } from '../api/client'
import { useAppError } from '../lib/AppError'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? ''

export function AuthImage({ src, alt, token }: { src: string; alt: string; token: string }) {
  const { report } = useAppError()
  const [url, setUrl] = useState<string>()
  const [error, setError] = useState('')

  useEffect(() => {
    let objectUrl: string | undefined
    let cancelled = false
    setUrl(undefined)
    setError('')
    if (!token) {
      setError('Image indisponible.')
      return
    }
    fetch(`${API_BASE_URL}${src}`, { headers: { Authorization: `Bearer ${token}`, Accept: 'image/*' } })
      .then(async response => {
        if (!response.ok) throw new ApiError('Image indisponible.', response.status)
        const blob = await response.blob()
        const nextUrl = URL.createObjectURL(blob)
        if (cancelled) {
          URL.revokeObjectURL(nextUrl)
          return
        }
        objectUrl = nextUrl
        setUrl(nextUrl)
      })
      .catch(reason => {
        if (!cancelled) {
          setError(reason instanceof ApiError ? reason.message : 'Image indisponible.')
          report(reason, 'L’illustration n’a pas pu être affichée.')
        }
      })
    return () => {
      cancelled = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [src, token, report])

  if (error) return <p className="help-text" role="status">{error}</p>
  if (!url) return <div className="page-image-skeleton" aria-busy="true">Chargement du visuel…</div>
  return <img className="page-image" src={url} alt={alt} />
}
