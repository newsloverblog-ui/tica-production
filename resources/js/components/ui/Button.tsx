import {Slot} from '@radix-ui/react-slot';
import {cva,type VariantProps} from 'class-variance-authority';
import type {ButtonHTMLAttributes} from 'react';
import {cn} from '../../lib/utils';
const buttonVariants=cva('focus-ring inline-flex min-h-11 items-center justify-center rounded-xl px-5 text-sm font-bold transition-[transform,background-color,border-color,box-shadow] motion-safe:hover:-translate-y-0.5 disabled:pointer-events-none disabled:opacity-50',{variants:{variant:{primary:'bg-primary text-white hover:bg-[#244ab7]',navy:'bg-primary text-white hover:bg-[#244ab7]',accent:'bg-peach text-warning ring-1 ring-orange/30 hover:bg-[#ffefcf]',outline:'border border-line bg-white text-navy hover:border-primary/30 hover:bg-primary-soft hover:text-primary',ghost:'bg-transparent text-navy hover:bg-primary-soft hover:text-primary',light:'border border-white/70 bg-white/10 text-white hover:bg-white/20'},size:{sm:'min-h-10 px-4',md:'min-h-12 px-6 text-base',lg:'min-h-14 px-8 text-base'}},defaultVariants:{variant:'primary',size:'md'}});
type ButtonProps=ButtonHTMLAttributes<HTMLButtonElement>&VariantProps<typeof buttonVariants>&{asChild?:boolean};
export function Button({className,variant,size,asChild,...props}:ButtonProps){const Component=asChild?Slot:'button';return <Component className={cn(buttonVariants({variant,size}),className)} {...props}/>;}
