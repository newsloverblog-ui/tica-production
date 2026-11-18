import {CalendarDays,MapPin,Search,TicketCheck,Users,WalletCards} from 'lucide-react';
import {EventCard} from '../components/app/EventCard';
import {Button} from '../components/ui/Button';
import type {HomeProps} from '../types/home';

export function HomePage({props}:{props:HomeProps}){
  const heroImage=props.heroImages[0];
  const dateRange=(days:number)=>{const start=new Date();const end=new Date(start);end.setDate(start.getDate()+days);const iso=(date:Date)=>date.toISOString().slice(0,10);return `${iso(start)} - ${iso(end)}`;};
  return <main>
    <section className="bg-navy text-white">
      <div className="shell grid min-h-[580px] items-center gap-12 py-14 lg:grid-cols-[1fr_.9fr] lg:py-16">
        <div className="max-w-2xl">
          <h1 className="display text-[clamp(42px,5.6vw,76px)] text-white">Find the events that move you.</h1>
          <p className="mt-6 max-w-xl text-[clamp(17px,1.5vw,21px)] leading-relaxed text-on-dark">Find the nights everyone will talk about. Or create the one that puts your name on the map.</p>
          <div className="mt-8 flex flex-wrap gap-3"><Button asChild size="lg"><a href={props.routes.discover}>Find an event</a></Button><Button asChild size="lg" variant="light"><a href={props.routes.create}>Create an event</a></Button></div>
        </div>
        {heroImage&&<img src={heroImage} alt="People enjoying a live event" fetchPriority="high" className="hidden aspect-[4/3] w-full rounded-xl object-cover lg:block"/>}
      </div>
    </section>

    <div className="shell py-6">
      <form action={props.routes.discover} className="grid overflow-hidden rounded-xl border border-line bg-white p-2 sm:grid-cols-2 lg:grid-cols-[1fr_1.1fr_1fr_1.25fr_auto]" role="search">
        <label className="flex min-h-16 items-center gap-3 border-line px-4 lg:border-r"><Search size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">What</span><input name="search-input" className="w-full bg-transparent font-semibold outline-none" placeholder="Search events"/></span></label>
        <label className="flex min-h-16 items-center gap-3 border-line px-4 lg:border-r"><MapPin size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">Where</span><select name="location" className="w-full bg-transparent font-semibold outline-none"><option value="">Anywhere</option>{props.cities.map(city=><option key={city}>{city}</option>)}</select></span></label>
        <label className="flex min-h-16 items-center gap-3 border-line px-4 lg:border-r"><CalendarDays size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">When</span><select name="dates" className="w-full bg-transparent font-semibold outline-none"><option value="">Any date</option><option value={dateRange(7)}>Next 7 days</option><option value={dateRange(30)}>This month</option><option value={dateRange(90)}>Next 3 months</option></select></span></label>
        <label className="flex min-h-16 items-center gap-3 px-4"><TicketCheck size={19} className="text-blue"/><span className="min-w-0 flex-1"><span className="block text-xs font-semibold text-soft">Category</span><select name="category" className="w-full bg-transparent font-semibold outline-none"><option value="">All experiences</option>{props.categories.map(category=><option value={category.slug} key={category.slug}>{category.name}</option>)}</select></span></label>
        <Button className="m-1" size="lg" type="submit">Search</Button>
      </form>
    </div>

    <section className="section shell">
      <h2 className="display text-[clamp(30px,4vw,44px)]">Browse by category</h2>
      <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">{props.categories.slice(0,6).map(category=><a href={`${props.routes.discover}?category=${encodeURIComponent(category.slug)}`} key={category.slug} className="focus-ring rounded-lg border border-line bg-white p-5 font-semibold transition-colors hover:border-primary hover:text-primary">{category.name}</a>)}</div>
    </section>

    <section className="section bg-tint"><div className="shell"><div className="flex items-center justify-between gap-4"><h2 className="display text-[clamp(30px,4vw,44px)]">Popular events</h2><a href={props.routes.discover} className="font-semibold text-blue">See all events</a></div>{props.events.length?<div className="mt-8 grid grid-cols-2 gap-x-3 gap-y-5 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">{props.events.map(event=><EventCard event={event} key={event.id}/>)}</div>:<div className="mt-8 rounded-xl bg-white p-10 text-center text-soft">New events will appear here as soon as they are published.</div>}</div></section>

    <section className="section shell"><div className="grid overflow-hidden rounded-xl bg-navy text-white lg:grid-cols-[1.05fr_.95fr]"><div className="p-8 sm:p-12 lg:p-14"><h2 className="display text-[clamp(32px,4vw,50px)] text-white">Run your event with TICA.</h2><p className="mt-5 max-w-lg leading-7 text-on-dark">Create your page, sell tickets, check in guests and track payouts from one workspace.</p><ul className="mt-7 space-y-3 text-sm font-semibold"><li className="flex gap-3"><TicketCheck className="text-orange"/> Free, paid and VIP tickets</li><li className="flex gap-3"><Users className="text-orange"/> Attendee and check-in tools</li><li className="flex gap-3"><WalletCards className="text-orange"/> Clear payout tracking</li></ul><Button asChild className="mt-8" size="lg"><a href={props.routes.create}>Create an event</a></Button></div>{heroImage&&<div className="relative min-h-[340px]"><img src={heroImage} alt="Crowd enjoying a live event" className="absolute inset-0 size-full object-cover" loading="lazy"/></div>}</div></section>
  </main>;
}
