# Sponsorbeheer

De site gebruikt één lijst in `data/sponsors.json`. Alle bestaande sponsorblokken zijn vervangen door herbruikbare componenten. Goud staat op iedere pagina, zilver op de sponsorenpagina, brons onderaan de toegewezen pagina.

## Beheer zonder code

Koppel de repository via https://pagescms.org aan je GitHub-account en open deze repository in Pages CMS. De configuratie staat in `.pages.yml`. Je kunt bedrijfsnamen, links, logo's, categorieën, pagina's, volgorde en actief-status aanpassen. Upload nieuwe afbeeldingen via de mediabibliotheek; de bestanden komen in `sponsoren/uploads`.

Pages CMS schrijft wijzigingen als GitHub-commits. Controleer welke branch en publicatie-instellingen Pages CMS gebruikt; bij GitHub Pages worden updates pas zichtbaar na publicatie. Geef alleen vertrouwde beheerders schrijfrechten op de repository.

Nieuwe pagina? Voeg een `data-sponsor-tier=\"gold\"` en `data-sponsor-tier=\"bronze\" data-sponsor-page=\"pagina-id\"` blok toe en laad `js/sponsors.js`.

Let op: bestaande logo's blijven op hun oude locaties staan. SVG-upload is uit veiligheidsoverwegingen niet toegestaan; bestaande SVG's blijven werken.
