import { useState, type FormEvent } from 'react'
import { ApiError, apiRequest } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { BusyButton } from '../components/BusyButton'
import { OperationProgress } from '../components/OperationProgress'

type ImportResult = { bookId: string; versionId: string; versionNumber: number; jobId: string; status: string }
type JobResult = { id: string; status: string; current_step: string; progress: number; error_message: string | null; metrics: Record<string, number> }

export function AdminBookImportPage({ embedded=false }: { embedded?:boolean }) {
  const { token } = useAuth()
  const [title, setTitle] = useState('Le Prophète KADIMA Bakenge')
  const [label, setLabel] = useState('Édition principale')
  const [description, setDescription] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [job, setJob] = useState<JobResult | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [phase, setPhase] = useState<'upload' | 'extract' | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!file) return setError('Sélectionnez le fichier PDF à importer.')
    setBusy(true)
    setError('')
    setJob(null)
    setPhase('upload')
    try {
      const form = new FormData()
      form.set('file', file)
      form.set('title', title)
      form.set('label', label)
      form.set('description', description)
      const uploaded = await apiRequest<{ data: ImportResult }>('/api/admin/books/imports', { method: 'POST', body: form }, token)
      setPhase('extract')
      const processed = await apiRequest<{ data: JobResult }>(`/api/admin/books/ingestion-jobs/${uploaded.data.jobId}/run`, { method: 'POST' }, token)
      setJob(processed.data)
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'L’import du livre a échoué.')
    } finally {
      setBusy(false)
      setPhase(null)
    }
  }

  const Root=embedded?'div':'main'
  return (
    <Root className={embedded?'embedded-workflow':'page admin-page'}>
      {!embedded&&<div className="page-heading"><div><span className="eyebrow">Administration</span><h1>Importer un livre</h1></div></div>}
      <div className="import-grid">
        <form className="admin-card" onSubmit={submit}>
          <h2>Nouvelle version</h2>
          <p>Le contenu sera extrait et placé en attente de validation. Il ne sera pas publié automatiquement.</p>
          {error && <div className="form-error" role="alert">{error}</div>}
          {busy && (
            <OperationProgress
              title={phase === 'extract' ? 'Extraction et découpage' : 'Envoi du PDF'}
              detail={phase === 'extract' ? 'Pages, passages et structure en cours de préparation…' : 'Téléversement du fichier vers le serveur…'}
              indeterminate
            />
          )}
          <label>Titre du livre<input value={title} onChange={(e) => setTitle(e.target.value)} required disabled={busy} /></label>
          <label>Libellé de version<input value={label} onChange={(e) => setLabel(e.target.value)} required disabled={busy} /></label>
          <label className="field-span-2">Description<textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={4} disabled={busy} /></label>
          <label className="field-span-2">Document PDF<input type="file" accept="application/pdf,.pdf" onChange={(e) => setFile(e.target.files?.[0] ?? null)} required disabled={busy} /></label>
          <BusyButton className="primary" type="submit" busy={busy} busyLabel={phase === 'extract' ? 'Extraction…' : 'Envoi…'}>Importer et extraire</BusyButton>
        </form>
        <section className="admin-card result-card">
          <h2>Résultat de l’ingestion</h2>
          {!job && !busy && <p>Aucune ingestion exécutée pendant cette session.</p>}
          {busy && !job && <OperationProgress title="Ingestion en cours" detail="Le détail s’affichera à la fin du traitement." indeterminate />}
          {job && <>
            <div className={`job-state ${job.status.toLowerCase()}`}>{job.status}</div>
            <OperationProgress
              title={job.current_step || 'Ingestion'}
              detail={job.error_message ?? undefined}
              current={job.progress}
              total={100}
              done={job.status === 'COMPLETED' || job.progress >= 100}
            />
            <dl><div><dt>Pages</dt><dd>{job.metrics.pages ?? '—'}</dd></div><div><dt>Passages</dt><dd>{job.metrics.chunks ?? '—'}</dd></div></dl>
            {job.error_message && <div className="form-error">{job.error_message}</div>}
          </>}
        </section>
      </div>
    </Root>
  )
}
