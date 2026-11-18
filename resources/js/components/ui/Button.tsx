import {Slot} from '@radix-ui/react-slot';
import {cva,type VariantProps} from 'class-variance-authority';
import type {ButtonHTMLAttributes} from 'react';
import {cn} from '../../lib/utils';
const buttonVariants=cva('focus-ring inline-flex min-h-11 items-center justify-center rounded-lg px-5 text-sm font-semibold transition-[background-color,border-color,color] disabled:pointer-events-none disabled:opacity-50',{variants:{variant:{primary:'bg-primary text-white hover:bg-[#244ab7]',navy:'bg-primary text-white hover:bg-[#244ab7]',accent:'border border-orange/30 bg-peach text-warning hover:bg-[#ffefcf]',outline:'border border-line bg-white text-navy hover:border-primary/40 hover:text-primary',ghost:'bg-transparent text-navy hover:bg-primary-soft hover:text-primary',light:'border border-white/60 bg-transparent text-white hover:bg-white/10'},size:{sm:'min-h-10 px-4',md:'min-h-12 px-6 text-base',lg:'min-h-14 px-8 text-base'}},defaultVariants:{variant:'primary',size:'md'}});
type ButtonProps=ButtonHTMLAttributes<HTMLButtonElement>&VariantProps<typeof buttonVariants>&{asChild?:boolean};
export function Button({className,variant,size,asChild,...props}:ButtonProps){const Component=asChild?Slot:'button';return <Component className={cn(buttonVariants({variant,size}),className)} {...props}/>;}
