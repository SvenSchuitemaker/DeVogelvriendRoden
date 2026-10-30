# Sponsorbeheer installeren zonder serverinstellingen

Voor deze variant is **geen Docker, database of toegang tot PHP-omgevingsvariabelen** nodig. Wel PHP 8.1+ en schrijfrechten voor de PHP-gebruiker.

## Eenmalig via FTP

1. Upload de website inclusief `admin/install.php` en `admin/index.php` naar de PHP-hosting. Maak vooraf een back-up van `data/sponsors.json`.
2. Maak op je eigen computer een nieuw bestand met de naam `install-code.php` met exact dit formaat (vervang de voorbeeldcode door een **zelfgekozen, willekeurige code van minimaal 24 tekens**, niet je wachtwoord):

   ```php
   <?php return 'VERVANG-DIT-DOOR-EEN-WILLEKEURIGE-CODE-VAN-MINSTENS-24-TEKENS';
   ```

3. Upload alleen dit bestand naar `admin/install-code.php` via FTP. **Zet dit bestand nooit in GitHub.**
4. Open via HTTPS `https://jouwdomein.nl/admin/install.php`, voer je installatiecode in, kies een gebruikersnaam en een sterk wachtwoord (minimaal 12 tekens).
5. De installatie maakt `admin/config.local.php` met een **wachtwoordhash** aan. De installatie is daarna automatisch vergrendeld.
6. Verwijder `admin/install-code.php` handmatig via FTP als het er nog is. Verwijder ook de tijdelijke lokale kopie.
7. Log in via `https://jouwdomein.nl/admin/`.

**Belangrijk:** `admin/config.local.php` moet op de server blijven staan en mag nooit naar GitHub. Het is een PHP-bestand dat instellingen retourneert zonder ze uit te printen. Beveilig de server zo dat PHP-bestanden daadwerkelijk worden uitgevoerd en nooit als tekst worden gedownload. Gebruik HTTPS.

## Serververeisten

- PHP 8.1+ en `fileinfo` voor uploads.
- De PHP-gebruiker moet `admin/` (eenmalig), `data/` en `sponsoren/uploads/` kunnen beschrijven.
- `sys_get_temp_dir()` moet schrijfbaar zijn voor de tijdelijke loginblokkade en schrijf-locks.
- Je hoeft **geen** `VOGELVRIEND_*`-omgevingsvariabelen in te stellen.
- Gebruik geen Live Server van VS Code: die voert geen PHP uit.
- De gegevens staan in `data/sponsors.json`, uploads in `sponsoren/uploads/`. Maak regelmatig back-ups; wijzigingen uit het dashboard gaan niet vanzelf naar GitHub.

## Beveiliging en herstel

Zonder `admin/install-code.php` kan niemand de installer starten. Na het aanmaken van een account is de installatie gesloten. Voor een nieuwe installatie moet `config.local.php` bewust via FTP worden verwijderd (maak eerst een back-up), en moet je opnieuw zelf een install-codebestand plaatsen. Verander dit niet via een publiek formulier.

**Controleer na installatie** login, fout wachtwoord, upload, toevoegen, bewerken, verwijderen en tonen op de juiste pagina.

Deze installatie vervangt de eerdere stappen met privéservermap en omgevingsvariabelen.
