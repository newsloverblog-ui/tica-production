import {CalendarDays,Search,ShieldCheck} from 'lucide-react';
import type {DirectoryProps} from '../types/directory';

export function DirectoryPage({props}:{props:DirectoryProps}){
  return <main>
    <section className="border-b border-line bg-white">
      <div className="shell py-12 sm:py-16">
        <h1 className="display max-w-5xl text-[clamp(38px,5vw,64px)]">{props.title}</h1>
        <p className="mt-4 max-w-2xl text-lg leading-8 text-soft">{props.intro}</p>
        {props.search&&<form action={props.search.action} className="mt-7 flex max-w-2xl rounded-xl border border-line bg-white p-2" role="search"><Search className="ml-3 self-center text-soft" size={19}/><label className="sr-only" htmlFor="directory-search">Search</label><input id="directory-search" className="min-w-0 flex-1 px-3 py-3 outline-none" name={props.search.name||'title'} defaultValue={props.search.value} placeholder={props.search.placeholder||'Search…'}/><button className="rounded-lg bg-primary px-5 font-semibold text-white">Search</button></form>}
      </div>
    </section>
    <section className="section shell">
      {props.variant==='faq'&&<div className="mx-auto max-w-4xl"><div className="grid gap-3">{props.faqs?.map((faq,index)=><details className="group rounded-xl border border-line bg-white p-5 sm:p-6" key={`${faq.question}-${index}`} open={index===0}><summary className="flex cursor-pointer list-none items-center justify-between gap-5 text-lg font-semibold"><span>{faq.question}</span><span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary transition-transform group-open:rotate-45" aria-hidden="true">+</span></summary><p className="mt-4 max-w-3xl leading-7 text-soft">{faq.answer}</p></details>)}</div>{!props.faqs?.length&&<Empty/>}</div>}
      {(props.variant==='blogs'||props.variant==='organisers')&&<>{props.cards?.length?<div className={props.variant==='blogs'?'grid gap-5 md:grid-cols-2 lg:grid-cols-3':'grid gap-5 sm:grid-cols-2 lg:grid-cols-4'}>{props.cards.map((card,index)=><a href={card.href} className="group overflow-hidden rounded-xl border border-line bg-white transition-colors hover:border-primary/40" key={`${card.title}-${index}`}>{card.image&&<img className={props.variant==='organisers'?'aspect-square w-full object-cover':'aspect-[16/10] w-full object-cover'} src={card.image} alt="" width="640" height={props.variant==='organisers'?640:400} loading="lazy"/>}<div className="p-5"><h2 className="text-xl font-semibold leading-tight text-navy">{card.title}</h2>{card.summary&&<p className="mt-3 line-clamp-3 leading-7 text-soft">{card.summary}</p>}<p className="mt-5 flex items-center gap-2 text-sm text-soft">{props.variant==='organisers'?<ShieldCheck size={16}/>:<CalendarDays size={16}/>} {card.meta}</p></div></a>)}</div>:<Empty/>}<Pager previousUrl={props.previousUrl} nextUrl={props.nextUrl}/></>}
      {props.variant==='article'&&<article className="mx-auto max-w-4xl">{props.articleImage&&<img className="mb-10 aspect-[16/8] w-full rounded-xl object-cover" src={props.articleImage} alt="" width="1200" height="600"/>}<div className="tica-prose" dangerouslySetInnerHTML={{__html:props.articleHtml||''}}/></article>}
    </section>
  </main>;
}

function Empty(){return <div className="rounded-xl bg-tint p-10 text-center"><h2 className="display text-2xl">Nothing here yet</h2><p className="mt-2 text-soft">Please check back soon.</p></div>}
function Pager({previousUrl,nextUrl}:{previousUrl?:string|null;nextUrl?:string|null}){if(!previousUrl&&!nextUrl)return null;return <nav className="mt-10 flex justify-center gap-3" aria-label="Pagination">{previousUrl&&<a href={previousUrl} className="focus-ring inline-flex min-h-12 items-center rounded-lg border border-line px-5 font-semibold">Previous</a>}{nextUrl&&<a href={nextUrl} className="focus-ring inline-flex min-h-12 items-center rounded-lg bg-primary px-5 font-semibold text-white">Next</a>}</nav>}
