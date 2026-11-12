import {clsx,type ClassValue} from 'clsx';
import {twMerge} from 'tailwind-merge';
export function cn(...inputs:ClassValue[]){return twMerge(clsx(inputs));}
export function formatMoney(kobo:number){return new Intl.NumberFormat('en-NG',{style:'currency',currency:'NGN',maximumFractionDigits:0}).format(kobo/100);}
