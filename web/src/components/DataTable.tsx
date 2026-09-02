import { useMemo, useState, type ReactNode } from 'react'
import { type MenuAction } from './ActionMenu'

export type Column<T> = { key:string; label:string; render:(row:T)=>ReactNode; priority?:'primary'|'secondary' }

function buttonClass(action: MenuAction) {
  if (action.kind) return action.kind
  if (action.danger) return 'danger'
  return 'secondary'
}

export function DataTable<T>({ rows, columns, identify, searchText, actions, empty='Aucune donnée disponible.' }: {
  rows:T[]; columns:Column<T>[]; identify:(row:T)=>string; searchText:(row:T)=>string; actions?:(row:T)=>MenuAction[]; empty?:string
}) {
  const [query,setQuery]=useState(''); const filtered=useMemo(()=>{const needle=query.trim().toLocaleLowerCase('fr');return needle?rows.filter(row=>searchText(row).toLocaleLowerCase('fr').includes(needle)):rows},[query,rows,searchText])
  return <div className="data-surface"><div className="table-toolbar"><label className="table-search"><span>⌕</span><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Rechercher dans la liste"/></label><span className="result-count">{filtered.length} résultat{filtered.length>1?'s':''}</span></div>
    {filtered.length===0?<div className="empty-state">{empty}</div>:<div className="responsive-table"><table><thead><tr>{columns.map(c=><th key={c.key}>{c.label}</th>)}{actions&&<th>Actions</th>}</tr></thead><tbody>{filtered.map(row=><tr key={identify(row)}>{columns.map(c=><td key={c.key} data-label={c.label} className={c.priority??''}>{c.render(row)}</td>)}{actions&&<td className="action-cell" data-label="Actions"><div className="row-actions compact">{actions(row).map((action,index)=><button type="button" key={index} className={buttonClass(action)} disabled={action.disabled} title={action.title} onClick={action.onClick}>{action.label}</button>)}</div></td>}</tr>)}</tbody></table></div>}
  </div>
}
