import {ArrowRight,CalendarDays,ChevronRight,MapPin,Search,Sparkles,TicketCheck,Users} from 'lucide-react';
import {EventCard} from '../components/app/EventCard';
import {Button} from '../components/ui/Button';
import type {HomeProps} from '../types/home';

const categoryDetails=[['Live sounds','♫'],['Move all night','✦'],['See something new','◒'],['Laugh out loud','☺'],['Taste the city','◇'],['Feel your best','◎']];

export function HomePage({props}:{props:HomeProps}){
  const heroEvents=props.heroImages.slice(0,3);
  const dateRange=(days:number)=>{const start=new Date();const end=new Date(start);end.setDate(start.getDate()+days);const iso=(date:Date)=>date.toISOString().slice(0,10);return `${iso(start)} - ${iso(end)}`;};
  return <main>
    <section className="noise relative overflow-hidden bg-navy text-white">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_75%_20%,rgb(15_76_158_/_65%),transparent_32%)]"/>
      <div className="shell relative grid min-h-[660px] items-center gap-12 py-16 lg:grid-cols-[1.02fr_.98fr] lg:py-20">
        <div className="max-w-2xl pb-20 lg:pb-28">
          <p className="mb-5 flex items-center gap-2 text-sm font-semibold text-on-dark"><Sparkles size={17} className="text-orange"/> Made for Nigeria&apos;s live culture</p>
          <h1 className="display text-[clamp(46px,6.4vw,92px)]">Events shape kulture. Let&apos;s build it together.</h1>
          <p className="mt-6 max-w-xl text-[clamp(17px,1.5vw,21px)] leading-relaxed text-on-dark">Find the nights everyone will talk about. Or create the one that puts your name on the map.</p>
          <div className="mt-8 flex flex-wrap gap-3"><Button asChild size="lg"><a href={props.routes.discover}>Find an event</a></Button><Button asChild size="lg" variant="light"><a href={props.routes.create}>Create an event</a></Button></div>
        </div>
        <div className="relative hidden h-[470px] lg:block" aria-hidden="true">{heroEvents.map((image,index)=><img key={image} src={image} alt="" className={`absolute top-1/2 aspect-[4/5] w-[44%] -translate-y-1/2 rounded-2xl object-cover shadow-float ${index===0?'left-0 -rotate-6':index===1?'left-[28%] z-10 -translate-y-[56%]':'right-0 rotate-6'}`}/>)}</div>
      </div>
    </section>

    <div className="shell relative z-10 -mt-[76px]">
      <form action={props.routes.discover} className="grid overflow-hidden rounded-[20px] bg-white p-2 shadow-float sm:grid-cols-2 lg:grid-cols-[1fr_1.1fr_1fr_1.25fr_auto]" role="search">
        <label className="flex min-h-16 items-center gap-3 border-line px-4 lg:border-r"><Search size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">What</span><input name="search-input" className="w-full bg-transparent font-semibold outline-none" placeholder="Search events"/></span></label>
        <label className="flex min-h-16 items-center gap-3 border-line px-4 lg:border-r"><MapPin size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">Where</span><select name="location" className="w-full bg-transparent font-semibold outline-none"><option value="">Anywhere</option>{props.cities.map(city=><option key={city}>{city}</option>)}</select></span></label>
        <label className="flex min-h-16 items-center gap-3 border-line px-4 lg:border-r"><CalendarDays size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">When</span><select name="dates" className="w-full bg-transparent font-semibold outline-none"><option value="">Any date</option><option value={dateRange(7)}>Next 7 days</option><option value={dateRange(30)}>This month</option><option value={dateRange(90)}>Next 3 months</option></select></span></label>
        <label className="flex min-h-16 items-center gap-3 px-4"><Sparkles size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">Category</span><select name="category" className="w-full bg-transparent font-semibold outline-none"><option value="">All experiences</option>{props.categories.map(category=><option value={category.slug} key={category.slug}>{category.name}</option>)}</select></span></label>
        <Button className="m-1 rounded-[14px]" size="lg" type="submit">Search</Button>
      </form>
    </div>

    <section className="section shell">
      <div className="flex items-end justify-between gap-4"><div><h2 className="display text-[clamp(30px,4vw,48px)]">What are you in the mood for?</h2><p className="mt-3 text-soft">Pick a feeling. We&apos;ll find the plan.</p></div></div>
      <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">{props.categories.slice(0,6).map((category,index)=><a href={`${props.routes.discover}?category=${encodeURIComponent(category.slug)}`} key={category.slug} className="focus-ring rounded-2xl bg-tint p-5 transition-colors hover:bg-peach"><span className="grid size-12 place-items-center rounded-full bg-white text-xl text-navy shadow-sm">{categoryDetails[index]?.[1]||'✦'}</span><b className="mt-4 block">{category.name}</b><small className="text-soft">{categoryDetails[index]?.[0]||'Find your next moment'}</small></a>)}</div>
    </section>

    <section className="section bg-tint"><div className="shell"><div className="flex items-end justify-between gap-4"><div><h2 className="display text-[clamp(30px,4vw,48px)]">Popular events</h2><p className="mt-3 text-soft">Where the city is heading next.</p></div><a href={props.routes.discover} className="hidden items-center gap-1 font-bold text-blue sm:flex">See all <ChevronRight size={18}/></a></div>{props.events.length?<div className="mt-8 grid grid-cols-2 gap-x-3 gap-y-5 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">{props.events.map(event=><EventCard event={event} key={event.id}/>)}</div>:<div className="mt-8 rounded-2xl bg-white p-10 text-center text-soft">New events will appear here as soon as they are published.</div>}</div></section>

    <section className="section shell"><div className="grid overflow-hidden rounded-[28px] bg-navy text-white lg:grid-cols-[1.05fr_.95fr]"><div className="p-8 sm:p-12 lg:p-16"><h2 className="display text-[clamp(32px,4vw,54px)]">You bring the people. TICA handles the tickets.</h2><p className="mt-5 max-w-lg leading-7 text-on-dark">Create your page, sell every ticket type, check guests in and see your money clearly—all in one place.</p><ul className="mt-7 space-y-3 text-sm font-semibold"><li className="flex gap-3"><TicketCheck className="text-orange"/> Sell free, paid and VIP tickets</li><li className="flex gap-3"><Users className="text-orange"/> Know exactly who&apos;s coming</li><li className="flex gap-3"><ArrowRight className="text-orange"/> Get paid directly to your bank</li></ul><Button asChild className="mt-8" size="lg"><a href={props.routes.create}>Create your event</a></Button></div><div className="relative min-h-[360px]"><img src={heroEvents[0]} alt="Crowd enjoying a live event" className="absolute inset-0 size-full object-cover"/></div></div></section>
  </main>;
}
