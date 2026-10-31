import { useEffect, useState } from "react";
import { NavLink, Route, Routes, useLocation } from "react-router-dom";
import { pages, type Sponsor } from "./types";
import { supabase } from "./lib/supabase";
import Admin from "./Admin";

type Content = Record<string,string>;
const asset=(path:string)=>"/"+path.replace(/^\/+/, "");
function Sponsors({page, tier, sponsors}:{page:string;tier:"gold"|"silver"|"bronze";sponsors:Sponsor[]}){
 const items=sponsors.filter(s=>s.active&&s.tier===tier&&(tier==="gold"||tier==="silver"||s.page===page)).sort((a,b)=>a.sort_order-b.sort_order);
 if(!items.length)return null;
 return <section className="sponsors"><div className="container"><h2>{tier==="gold"?"Onze trotse hoofdsponsoren":tier==="silver"?"Onze zilveren sponsoren":"Onze paginasponsoren"}</h2><div className="sponsor-grid">{items.map(s=><a key={s.id} href={s.url} target="_blank" rel="noopener noreferrer" aria-label={`Website van ${s.name}`}><img src={s.image.startsWith("http")?s.image:asset(s.image)} alt={`Logo van ${s.name}`} loading="lazy"/></a>)}</div></div></section>;
}
function PublicPage({slug, content, sponsors}:{slug:string;content:Content;sponsors:Sponsor[]}){
 const page=slug||"index";
 return <><main className="container legacy-content" dangerouslySetInnerHTML={{__html:content[page]??"<h1>Pagina niet gevonden</h1>"}}/><Sponsors page={page} tier="gold" sponsors={sponsors}/><Sponsors page={page} tier="silver" sponsors={sponsors}/><Sponsors page={page} tier="bronze" sponsors={sponsors}/></>;
}
function Layout({children}:{children:React.ReactNode}){
 const [menu,setMenu]=useState(false);const location=useLocation();
 useEffect(()=>{setMenu(false);window.scrollTo(0,0);},[location.pathname]);
 return <><header><nav className="container nav"><NavLink to="/" aria-label="Home"><img className="brand" src="/images/logoZonderAchtergrond.png" alt="De Vogelvriend Roden"/></NavLink><button className="menu-toggle" type="button" onClick={()=>setMenu(x=>!x)} aria-expanded={menu} aria-controls="navigation">☰ Menu</button><div id="navigation" className={menu?"links open":"links"}>{pages.map(p=><NavLink key={p.slug} to={p.slug?"/"+p.slug:"/"} end>{p.title}</NavLink>)}</div></nav></header>{children}<footer>© De Vogelvriend Roden e.o. · <NavLink to="/admin">Beheer</NavLink></footer></>;
}
export default function App(){
 const [content,setContent]=useState<Content>({});
 const [sponsors,setSponsors]=useState<Sponsor[]>([]);
 const [error,setError]=useState("");
 useEffect(()=>{
  fetch("/content.json").then(r=>{if(!r.ok)throw Error("Inhoud kon niet geladen worden");return r.json()}).then(setContent).catch(e=>setError(String(e)));
  const load=async()=>{
   if(supabase){const {data,error}=await supabase.from("sponsors").select("*");if(error){setError("Sponsoren konden niet geladen worden: "+error.message);return;}setSponsors(data??[]);}
   else {const r=await fetch("/sponsors.json");const rows=await r.json();setSponsors(rows.map((s:Record<string,unknown>,i:number)=>({id:String(i),name:s.name,url:s.url,image:s.image,tier:s.tier,page:s.page||null,sort_order:s.order,active:s.active!==false})))}
  };
  load().catch(e=>setError(String(e)));
 },[]);
 return <Layout>{error&&<div role="alert" className="notice">{error}</div>}<Routes>{pages.map(p=><Route key={p.slug} path={p.slug?"/"+p.slug:"/"} element={<PublicPage slug={p.slug} content={content} sponsors={sponsors}/>}/>)}<Route path="/admin/*" element={<Admin/>}/><Route path="*" element={<main className="container"><h1>Pagina niet gevonden</h1></main>}/></Routes></Layout>;
}
