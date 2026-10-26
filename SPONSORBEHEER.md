# Eigen sponsorbeheer — De Vogelvriend Roden

De website krijgt een zelfgebouwd beheerpaneel op **https://jouwdomein.nl/admin/**.
Er is geen Pages CMS, externe database of GitHub-koppeling nodig voor het beheer.

## Hostingvereisten

- PHP 8.1 of nieuwer, met fileinfo-extensie.
- HTTPS op het domein.
- PHP moet mogen schrijven in de mappen data/ en sponsoren/uploads/.
- Een schrijfbare privémap buiten de publiek toegankelijke document root.
- De mogelijkheid om PHP-omgevingsvariabelen in te stellen, bijvoorbeeld via het hostingpaneel of PHP-FPM.

**GitHub Pages ondersteunt geen PHP.** Controleer of je hosting PHP en omgevingsvariabelen ondersteunt.

## Installatie

1. Maak buiten de public/document root een privémap (bijvoorbeeld /home/account/private-vogelvriend) en geef de PHP-gebruiker schrijfrechten.
2. Geef de PHP-gebruiker schrijfrechten op data/ en sponsoren/uploads/.
3. Genereer met PHP CLI een password_hash met PASSWORD_DEFAULT, bij voorkeur via een afgeschermde terminal, en sla geen leesbaar wachtwoord in de repository op.
4. Stel de volgende serveromgevingsvariabelen in:

   | Variabele | Inhoud |
   | --- | --- |
   | VOGELVRIEND_PRIVATE_DIR | Absoluut pad naar de privémap buiten de document root |
   | VOGELVRIEND_ADMIN_USER | Beheerdersgebruikersnaam (standaard admin) |
   | VOGELVRIEND_ADMIN_PASSWORD_HASH | De PHP password_hash van het eigen wachtwoord |

5. Controleer dat de PHP-server de variabelen ontvangt. Open daarna https://jouwdomein.nl/admin/ en log in.
6. Maak periodiek back-ups van data/sponsors.json en sponsoren/uploads/. Wijzigingen vanuit de admin worden direct op de server opgeslagen en worden **niet automatisch teruggezet in GitHub**.

Als de configuratie ontbreekt, blijft de admin uit veiligheid uitgeschakeld.

## Beheerfuncties

- Sponsoren toevoegen, bewerken, uitschakelen en verwijderen.
- Bedrijfsnaam, link, logo, volgorde, categorie en pagina instellen.
- Gold: hoofdsponsoren op iedere pagina.
- Silver: sponsorenpagina.
- Brons: gekozen pagina.
- JPG-, PNG- en WebP-logo's uploaden (maximaal 5 MB).
- Bestaande logo's selecteren; bestaande SVG-bestanden blijven bruikbaar, maar SVG-upload is niet toegestaan.

De admin past data/sponsors.json aan. De bestaande pagina's lezen dat bestand direct met js/sponsors.js. Voor veranderingen in sponsorinformatie is geen codewijziging nodig.

## Beveiliging

- PHP-sessies met veilige, HttpOnly- en SameSite-cookies.
- Password hashing, CSRF-controle en een eenvoudige blokkade na vijf mislukte loginpogingen per IP.
- HTTPS verplicht buiten localhost.
- Privémap buiten de public root en geen inloggegevens in GitHub.
- Bestandscontrole bij upload en willekeurige bestandsnamen.
- Uploadmap blokkeert PHP-bestanden op Apache via .htaccess. Nginx-hosting moet uitvoerbare bestanden in deze map ook aan de serverkant blokkeren.
- Bij het verwijderen van een sponsor blijven logo's voor hergebruik behouden.

## Testen na deployment

- [ ] Admin-login werkt op het domein met HTTPS.
- [ ] Onjuist wachtwoord geeft geen toegang.
- [ ] Toevoegen, bewerken, uitschakelen en verwijderen werkt.
- [ ] Een geüpload logo verschijnt op de bedoelde pagina.
- [ ] Gold, Silver, Brons en sortering werken correct.
- [ ] Externe sponsorlink opent in een nieuw tabblad.
- [ ] Uitloggen en herladen vragen opnieuw om een login.
- [ ] Er zijn back-ups van data/ en sponsoren/uploads/.

De PR levert de code. De installatie kan pas worden afgerond wanneer het hostingtype en de configuratiemogelijkheden bekend zijn.
