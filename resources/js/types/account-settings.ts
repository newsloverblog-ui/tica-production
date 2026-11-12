export type AccountNavItem = {label:string;url:string;active?:boolean};
export type AccountField = {name:string;label:string;type?:string;value?:string;placeholder?:string;help?:string;required?:boolean;wide?:boolean;error?:string};
export type AccountSettingsProps = {eyebrow:string;title:string;intro:string;nav:AccountNavItem[];csrf:string;success?:string;variant:'profile'|'password'|'wishlist';action?:string;photoUrl?:string;fields?:AccountField[];wishlist?:{id:number;title:string;eventUrl:string;removeUrl:string}[]};
