export type SponsorTier = "gold" | "silver" | "bronze";
export type Sponsor = { id: string; name: string; url: string; image: string; tier: SponsorTier; page: string | null; sort_order: number; active: boolean };
export const pages = [{slug:"",title:"Home"},{slug:"bestuur",title:"Bestuur"},{slug:"agenda",title:"Agenda"},{slug:"tentoonstelling",title:"Tentoonstelling"},{slug:"sponsoren",title:"Sponsoren"},{slug:"ringen",title:"Ringen"},{slug:"lidworden",title:"Lid worden"},{slug:"contact",title:"Contact"}];
