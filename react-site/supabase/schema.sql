-- Uitvoeren in uitsluitend het nieuwe Supabase-project voor De Vogelvriend Roden.
create table if not exists public.admin_users (
  user_id uuid primary key references auth.users(id) on delete cascade,
  created_at timestamptz not null default now()
);
alter table public.admin_users enable row level security;
create policy "Beheerders lezen eigen rol" on public.admin_users
  for select to authenticated using ((select auth.uid()) = user_id);

create table if not exists public.sponsors (
  id uuid primary key default gen_random_uuid(),
  name text not null check (length(trim(name)) between 1 and 180),
  url text not null check (url ~* '^https?://'),
  image text not null,
  tier text not null check (tier in ('gold','silver','bronze')),
  page text check (page in ('index','bestuur','agenda','tentoonstelling','sponsoren','ringen','lidworden','contact')),
  sort_order integer not null default 0,
  active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint bronze_requires_page check ((tier = 'bronze' and page is not null) or (tier <> 'bronze' and page is null))
);
create index if not exists sponsors_tier_page_order_idx on public.sponsors(tier,page,sort_order);
alter table public.sponsors enable row level security;
create policy "Publieke actieve sponsoren" on public.sponsors
  for select to anon,authenticated using (active or exists (select 1 from public.admin_users a where a.user_id = (select auth.uid())));
create policy "Beheerders voegen sponsoren toe" on public.sponsors
  for insert to authenticated with check (exists (select 1 from public.admin_users a where a.user_id = (select auth.uid())));
create policy "Beheerders wijzigen sponsoren" on public.sponsors
  for update to authenticated using (exists (select 1 from public.admin_users a where a.user_id = (select auth.uid())))
  with check (exists (select 1 from public.admin_users a where a.user_id = (select auth.uid())));
create policy "Beheerders verwijderen sponsoren" on public.sponsors
  for delete to authenticated using (exists (select 1 from public.admin_users a where a.user_id = (select auth.uid())));

insert into storage.buckets(id,name,public,file_size_limit,allowed_mime_types)
values ('sponsor-logos','sponsor-logos',true,5242880,array['image/jpeg','image/png','image/webp','image/gif'])
on conflict (id) do nothing;
create policy "Publieke sponsorlogo weergave" on storage.objects
 for select to anon,authenticated using (bucket_id='sponsor-logos');
create policy "Beheerders uploaden logo" on storage.objects
 for insert to authenticated with check (bucket_id='sponsor-logos' and exists (select 1 from public.admin_users a where a.user_id=(select auth.uid())));
create policy "Beheerders verwijderen logo" on storage.objects
 for delete to authenticated using (bucket_id='sponsor-logos' and exists (select 1 from public.admin_users a where a.user_id=(select auth.uid())));
