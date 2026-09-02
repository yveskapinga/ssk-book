import { PageTabs } from '../components/PageTabs'
import { StatGrid } from '../components/StatGrid'
import { useState } from 'react'

export function ProgressWorkspacePage(){const[tab,setTab]=useState<'progress'|'bookmarks'|'notes'>('progress');return <main className="workspace-page"><div className="page-title"><div><span className="eyebrow">Mon activité</span><h1>Progression</h1></div></div><StatGrid items={[{label:'Livre lu',value:'0 %'},{label:'Passages favoris',value:0},{label:'Notes personnelles',value:0},{label:'Meilleur score',value:'—'}]}/><PageTabs tabs={[{id:'progress',label:'Parcours'},{id:'bookmarks',label:'Favoris',count:0},{id:'notes',label:'Notes',count:0}]} active={tab} onChange={setTab}/><div className="empty-panel"><strong>{tab==='progress'?'Votre parcours commence ici':tab==='bookmarks'?'Aucun favori':'Aucune note'}</strong><p>Les données seront enregistrées automatiquement au fil de votre lecture.</p></div></main>}
