export type SupportTicketItem={id:number;subject:string;status:string;statusTone:'orange'|'blue'|'green';messageUrl?:string};
export type SupportProps={mode:'list'|'create';csrf:string;listUrl:string;createUrl:string;storeUrl?:string;email?:string;success?:string;errors?:Record<string,string>;tickets?:SupportTicketItem[]};
