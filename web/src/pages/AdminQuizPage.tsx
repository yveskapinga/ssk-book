import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { apiRequest, ApiError } from '../api/client'
import { SearchableSelect } from '../components/SearchableSelect'
import { BusyButton } from '../components/BusyButton'
import { useAuth } from '../auth/AuthContext'

type QuizRow = { id:string; title:string; status:string; created_at:string; book_title:string; slug:string; question_count:number|string }
type Book = { slug:string; title:string }
type ChoiceDraft = { label:string; isCorrect:boolean }
type QuestionDraft = { prompt:string; type:'MULTIPLE_CHOICE'; choices:ChoiceDraft[] }

const emptyQuestion = (): QuestionDraft => ({ prompt:'', type:'MULTIPLE_CHOICE', choices:[{label:'',isCorrect:true},{label:'',isCorrect:false}] })

export function AdminQuizPage() {
  const { token } = useAuth(); const cache = useQueryClient()
  const [error,setError]=useState(''); const [feedback,setFeedback]=useState(''); const [busy,setBusy]=useState(false)
  const [title,setTitle]=useState(''); const [description,setDescription]=useState(''); const [bookSlug,setBookSlug]=useState('')
  const [questions,setQuestions]=useState<QuestionDraft[]>([emptyQuestion()])
  const quizzes = useQuery({ queryKey:['admin-quizzes'], queryFn:()=>apiRequest<{data:{items:QuizRow[]}}>('/api/admin/quizzes', {}, token).then(r=>r.data.items) })
  const library = useQuery({ queryKey:['library'], queryFn:()=>apiRequest<{data:{items:Book[]}}>('/api/library', {}, token).then(r=>r.data.items) })
  const versions = useQuery({ queryKey:['book-versions'], queryFn:()=>apiRequest<{data:{items:{slug:string;book_title:string}[]}}>('/api/admin/book-versions?limit=100', {}, token).then(r=>r.data.items) })
  const books = (library.data && library.data.length>0)
    ? library.data
    : Array.from(new Map((versions.data??[]).map(v=>[v.slug, {slug:v.slug, title:v.book_title}])).values())

  async function create(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setFeedback('')
    try {
      await apiRequest('/api/admin/quizzes', { method:'POST', body: JSON.stringify({
        bookSlug: bookSlug || books[0]?.slug,
        title, description, questions: questions.map(q=>({ prompt:q.prompt, type:q.type, choices:q.choices })),
      }) }, token)
      setTitle(''); setDescription(''); setQuestions([emptyQuestion()])
      setFeedback('Quiz enregistré en brouillon. Soumettez-le à revue avant publication.')
      await cache.invalidateQueries({ queryKey:['admin-quizzes'] })
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Création impossible.') }
    finally { setBusy(false) }
  }

  async function act(id:string, path:'review'|'publish') {
    setBusy(true); setError(''); setFeedback('')
    try {
      await apiRequest(`/api/admin/quizzes/${id}/${path}`, { method:'POST' }, token)
      setFeedback(path==='review' ? 'Quiz soumis à revue humaine.' : 'Quiz publié.')
      await cache.invalidateQueries({ queryKey:['admin-quizzes'] })
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Action impossible.') }
    finally { setBusy(false) }
  }

  return <section className="quiz-admin">
    <div className="section-heading"><div><h2>Quiz du livre</h2><p>Les questions restent en brouillon jusqu’à une revue humaine. Aucune génération automatique n’est publiée.</p></div></div>
    {error && <div className="form-error" role="alert">{error}</div>}
    {feedback && <div className="form-success" role="status">{feedback}</div>}
    <form className="admin-card quiz-form" onSubmit={create}>
      <h3>Nouveau quiz</h3>
      <SearchableSelect
        label="Livre"
        value={bookSlug}
        onChange={setBookSlug}
        options={books.map(book => ({ value: book.slug, label: book.title }))}
        placeholder="Choisir le livre"
        allowEmpty
        emptyLabel="Choisir le livre"
        required={(library.data?.length ?? 0) > 0 || books.length > 0}
      />
      <label>Titre<input value={title} onChange={e=>setTitle(e.target.value)} minLength={2} required/></label>
      <label className="field-span-2">Description<textarea value={description} onChange={e=>setDescription(e.target.value)} rows={2}/></label>
      {questions.map((question, qi)=>
        <fieldset key={qi} className="field-span-2">
          <legend>Question {qi+1}</legend>
          <label>Énoncé<input value={question.prompt} onChange={e=>setQuestions(questions.map((q,i)=>i===qi?{...q,prompt:e.target.value}:q))} minLength={3} required/></label>
          {question.choices.map((choice, ci)=>
            <label key={ci} className="choice-row"><input type="radio" name={`correct-${qi}`} checked={choice.isCorrect} onChange={()=>setQuestions(questions.map((q,i)=>i===qi?{...q,choices:q.choices.map((c,j)=>({...c,isCorrect:j===ci}))}:q))}/>
              <input value={choice.label} onChange={e=>setQuestions(questions.map((q,i)=>i===qi?{...q,choices:q.choices.map((c,j)=>j===ci?{...c,label:e.target.value}:c)}:q))} placeholder={`Choix ${ci+1}`} required/>
            </label>
          )}
          <button type="button" className="secondary" onClick={()=>setQuestions(questions.map((q,i)=>i===qi?{...q,choices:[...q.choices,{label:'',isCorrect:false}]}:q))}>Ajouter un choix</button>
        </fieldset>
      )}
      <div className="row-actions field-span-2">
        <button type="button" className="secondary" onClick={()=>setQuestions([...questions, emptyQuestion()])} disabled={busy}>Ajouter une question</button>
        <BusyButton className="primary" type="submit" busy={busy} busyLabel="Enregistrement…">Enregistrer le brouillon</BusyButton>
      </div>
    </form>
    <div className="quiz-admin-list">
      {(quizzes.data??[]).map(q=>
        <article key={q.id} className="review-card">
          <div className="review-card-head">
            <div><strong>{q.title}</strong><small>{q.book_title} · {q.question_count} question{Number(q.question_count)>1?'s':''}</small></div>
            <span className={`status ${q.status==='PUBLISHED'?'active':''}`}>{q.status==='DRAFT'?'Brouillon':q.status==='REVIEW_REQUIRED'?'En attente de revue':q.status==='PUBLISHED'?'Publié':q.status}</span>
          </div>
          {(q.status==='DRAFT' || q.status==='REVIEW_REQUIRED') && <div className="row-actions">
            {q.status==='DRAFT' && <BusyButton className="secondary" busy={busy} busyLabel="Envoi…" disabled={busy} onClick={()=>void act(q.id,'review')}>Soumettre à revue</BusyButton>}
            {q.status==='REVIEW_REQUIRED' && <BusyButton className="primary" busy={busy} busyLabel="Publication…" disabled={busy} onClick={()=>void act(q.id,'publish')}>Publier</BusyButton>}
          </div>}
        </article>
      )}
      {!quizzes.isPending && (quizzes.data??[]).length===0 && <div className="empty-panel"><strong>Aucun quiz créé.</strong><p>Enregistrez d’abord un brouillon ci-dessus.</p></div>}
    </div>
  </section>
}
