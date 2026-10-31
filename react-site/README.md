# De Vogelvriend Roden — React + TypeScript + Supabase

Deze map bevat de nieuwe website. De huidige HTML/PHP-versie blijft voorlopig ongewijzigd aan de repository-root, zodat de live site pas na testen wordt overgezet.

## Lokaal
`cd react-site && npm install && npm run build && npm run dev`

Het buildscript zet de bestaande pagina-inhoud om naar een tijdelijke content-JSON en kopieert originele afbeeldingen, sponsorlogo's en downloads uit de repository. De navigatie, paginaweergave en het sponsorbeheer zijn React-componenten. De oude pagina-inhoud wordt **voorlopig** als vertrouwde, lokale HTML gebruikt; herschrijf deze later naar native JSX / database-content om de migratie af te ronden.

## Supabase
1. Maak een **nieuw Supabase-project** voor De Vogelvriend Roden (gebruik niet de database van een andere applicatie).
2. Voer `supabase/schema.sql` uit in de SQL-editor van het nieuwe project.
3. Importeer de huidige data met `node scripts/import-sponsors.mjs` (zie hieronder). Gebruik hiervoor uitsluitend lokaal een service-role key, nooit in frontend-code of Git.
4. Maak een beheerder aan via Supabase Authentication > Users, kopieer zijn UUID en voer in de SQL-editor uit: `insert into public.admin_users(user_id) values ('UUID_HIER');`
5. Kopieer `.env.example` naar `.env`, vul de project-URL en **publishable key** in; gebruik NOOIT een secret/service-role key in VITE_-variabelen.
6. Start `npm run dev`; bezoek `/admin`.

## Migreren van sponsoren
Lokaal: `SUPABASE_URL=https://... SUPABASE_SERVICE_ROLE_KEY=... node scripts/import-sponsors.mjs`.
Sla de service-role key niet op in bestanden die naar Git gaan. Bestaande plaatjes blijven via het buildscript in de statische map beschikbaar. Nieuwe plaatjes worden door /admin in de Supabase Storage bucket geplaatst.

## Hosting
`npm run build` maakt `react-site/dist` aan. Upload de **inhoud** van die map naar de hosting, met een SPA-fallback naar `index.html` voor routes als `/admin`. Een voorbeeld voor Apache staat in `public/.htaccess`; controleer of je host Apache gebruikt. Maak eerst een volledige backup van de live website.

## Openstaande migratiepunten
- Bestaande inhoud is nog geen native JSX, maar een tijdelijke migratielaag.
- Het oude CGI/FormMail-contactformulier is bewust uitgeschakeld; de contactpagina verwijst tijdelijk naar het e-mailadres. Koppel later een beveiligde Edge Function met spambeveiliging aan een React-formulier.
- Test links, downloads, SEO en routes vóór livegang.
- De live host moet SPA-routing ondersteunen. PHP is voor het nieuwe sponsorbeheer niet nodig.
