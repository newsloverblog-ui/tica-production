export type SupportTicketItem={id:number;subject:string;status:string;destination:string;messageUrl:string};
export type SupportBookingItem={id:number;eventTitle:string;reference:string};
export type SupportMessageItem={id:number;author:string;body:string;time:string;fromCustomer:boolean;attachmentUrl?:string};
export type SupportConversation={subject:string;destination:string;status:string;initialMessage:string;createdAt:string;messages:SupportMessageItem[]};
export type SupportProps={mode:'list'|'create'|'conversation';csrf:string;listUrl:string;createUrl:string;storeUrl?:string;replyUrl?:string;email?:string;success?:string;errors?:Record<string,string>;tickets?:SupportTicketItem[];bookings?:SupportBookingItem[];initialRecipient?:'tica'|'organizer';conversation?:SupportConversation;canReply?:boolean};
