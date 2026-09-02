import { useEffect, useRef, useState } from 'react'

export type MenuAction = { label:string; onClick:()=>void; danger?:boolean; disabled?:boolean }

export function ActionMenu({ actions, label='Actions' }: { actions:MenuAction[]; label?:string }) {
  const [open,setOpen]=useState(false); const ref=useRef<HTMLDivElement>(null)
  useEffect(()=>{function close(event:MouseEvent){if(!ref.current?.contains(event.target as Node))setOpen(false)}document.addEventListener('mousedown',close);return()=>document.removeEventListener('mousedown',close)},[])
  return <div className="action-menu" ref={ref}><button className="kebab" aria-label={label} aria-haspopup="menu" aria-expanded={open} onClick={()=>setOpen(!open)}>⋮</button>{open&&<div className="action-popover" role="menu">{actions.map((action,index)=><button key={index} role="menuitem" className={action.danger?'danger':''} disabled={action.disabled} onClick={()=>{setOpen(false);action.onClick()}}>{action.label}</button>)}</div>}</div>
}
