import { Check, Mail, MapPin, Phone, Send, ShieldCheck, Sparkles, TicketCheck } from 'lucide-react';
import type { ContentPageProps } from '../types/content';

const pageCopy = {
  about: {
    title: 'More than tickets. A home for the people who make events happen.',
    intro: 'TICA exists to help Nigerian culture move—one room, one crowd and one unforgettable night at a time.',
    items: [
      ['Built close to the culture', 'Tools shaped around how people discover, share and attend events here.'],
      ['Clear by default', 'Straightforward fees, useful information and no confusing steps.'],
      ['Organisers come first', 'Everything an organiser needs to publish, sell, understand and grow.'],
    ],
  },
  pricing: {
    title: 'One simple fee. No surprises.',
    intro: 'Free events are free. For paid events, choose who covers the service fee and see every amount before you publish.',
    items: [
      ['Free events', 'Create and manage free events without a platform charge.'],
      ['Paid events', 'A clear service fee shown before a ticket is purchased.'],
      ['Running something bigger?', 'Talk to TICA about high-volume events and tailored support.'],
    ],
  },
};

function ContactPage({ props }: { props: ContentPageProps }) {
  const contact = props.contact!;
  const field = (name: string) => contact.errors?.[name];
  const primaryEmail = contact.email.split(',')[0].trim();
  const primaryPhone = contact.phone.split(',')[0].trim();
  return <>
    <section className="overflow-hidden bg-navy text-white"><div className="shell py-16 sm:py-24"><h1 className="display max-w-5xl text-[clamp(42px,6vw,82px)]">Talk to us</h1><p className="mt-6 max-w-2xl text-lg leading-8 text-on-dark">Questions about tickets, your event or a partnership? Our team is ready to help.</p><div className="mt-8 flex flex-wrap gap-3"><a className="inline-flex min-h-12 items-center gap-2 rounded-xl bg-white px-5 font-bold text-navy" href={`mailto:${primaryEmail}`}><Mail size={18}/> Email us</a><a className="inline-flex min-h-12 items-center gap-2 rounded-xl bg-orange px-5 font-bold text-navy" href={`tel:${primaryPhone}`}><Phone size={18}/> Call TICA</a></div></div></section>
    <section className="section shell grid gap-12 lg:grid-cols-[.72fr_1.28fr]">
      <aside><h2 className="display text-3xl">We’re easy to reach.</h2><p className="mt-4 max-w-md leading-7 text-soft">Tell us what you need and include the event or order name where possible.</p><div className="mt-8 grid gap-3">{[[MapPin,contact.address],[Mail,contact.email],[Phone,contact.phone]].map(([Icon,value])=><div className="flex items-start gap-4 rounded-2xl bg-tint p-5" key={String(value)}><span className="grid size-11 shrink-0 place-items-center rounded-full bg-peach text-orange"><Icon size={19}/></span><span className="pt-2 font-semibold">{String(value)}</span></div>)}</div></aside>
      <div className="rounded-[24px] border border-line bg-white p-6 shadow-card sm:p-9"><h2 className="display text-3xl">Send a message</h2><p className="mt-3 text-soft">We usually reply within one working day.</p>{contact.success&&<p className="mt-5 rounded-xl bg-green-50 p-4 font-semibold text-green-800">{contact.success}</p>}{contact.error&&<p className="mt-5 rounded-xl bg-red-50 p-4 font-semibold text-red-800">{contact.error}</p>}<form action={contact.action} method="post" className="mt-7 grid gap-5"><input type="hidden" name="_token" value={contact.csrf}/><div className="grid gap-5 sm:grid-cols-2"><label className="font-semibold">Full name<input className="mt-2 min-h-12 w-full rounded-xl border border-line px-4 outline-none focus:border-orange" name="name" defaultValue={contact.old?.name}/>{field('name')&&<small className="mt-1 block text-red-700">{field('name')}</small>}</label><label className="font-semibold">Email address<input type="email" className="mt-2 min-h-12 w-full rounded-xl border border-line px-4 outline-none focus:border-orange" name="email" defaultValue={contact.old?.email}/>{field('email')&&<small className="mt-1 block text-red-700">{field('email')}</small>}</label></div><label className="font-semibold">Subject<input className="mt-2 min-h-12 w-full rounded-xl border border-line px-4 outline-none focus:border-orange" name="subject" defaultValue={contact.old?.subject}/>{field('subject')&&<small className="mt-1 block text-red-700">{field('subject')}</small>}</label><label className="font-semibold">Message<textarea rows={6} className="mt-2 w-full rounded-xl border border-line p-4 outline-none focus:border-orange" name="message" defaultValue={contact.old?.message}/>{field('message')&&<small className="mt-1 block text-red-700">{field('message')}</small>}</label>{contact.captchaHtml&&<div dangerouslySetInnerHTML={{__html:contact.captchaHtml}}/>}<button className="inline-flex min-h-13 items-center justify-center gap-2 rounded-xl bg-orange px-6 font-bold text-navy sm:w-fit" type="submit">Send message <Send size={18}/></button></form></div>
    </section>
  </>;
}

export function ContentPage({ props }: { props: ContentPageProps }) {
  if (props.page === 'contact') return <ContactPage props={props}/>;
  const data = pageCopy[props.page];
  const icons = [Sparkles, ShieldCheck, TicketCheck];
  return <><section className="overflow-hidden bg-navy text-white"><div className="shell py-16 sm:py-24"><h1 className="display max-w-5xl text-[clamp(42px,6vw,82px)]">{data.title}</h1><p className="mt-6 max-w-2xl text-lg leading-8 text-on-dark">{data.intro}</p></div></section><section className="section shell"><div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">{data.items.map((item,index)=>{const Icon=icons[index];return <article className="rounded-2xl bg-tint p-7" key={item[0]}><span className="grid size-11 place-items-center rounded-full bg-peach text-orange"><Icon size={19}/></span><h2 className="mt-5 text-xl font-bold">{item[0]}</h2><p className="mt-3 leading-7 text-soft">{item[1]}</p></article>})}</div>{props.page==='pricing'&&<div className="mt-12 rounded-[28px] bg-orange p-8 sm:p-12"><h2 className="display text-4xl">Know what every ticket earns.</h2><div className="mt-6 grid gap-4 sm:grid-cols-3"><div className="rounded-xl bg-white p-4"><span className="text-sm text-soft">Ticket price</span><b className="mt-1 block text-xl">₦15,000</b></div><div className="rounded-xl bg-white/70 p-4"><span className="text-sm">Buyer sees</span><b className="mt-1 block text-xl">Before payment</b></div><div className="rounded-xl bg-navy p-4 text-white"><span className="text-sm text-on-dark">Your proceeds</span><b className="mt-1 block text-xl">Shown clearly</b></div></div></div>}</section></>;
}
