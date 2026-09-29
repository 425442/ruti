<?php
/**
 * Ruti – Server-Konfiguration (Vorlage)
 *
 * 1. Diese Datei kopieren und als  ruti-config.php  speichern –
 *    am besten AUSSERHALB von public_html, also z. B. hier:
 *      /home/<dein-benutzer>/domains/bollmer.click/ruti-config.php
 *    (dort ist sie aus dem Internet nicht abrufbar und wird beim
 *    Git-Deployment nicht überschrieben).
 * 2. Die Werte unten eintragen.
 *
 * Die echte Konfigurationsdatei niemals ins GitHub-Repository hochladen.
 */
return [
    // MySQL-Datenbank aus dem Hostinger hPanel (Datenbanken → MySQL-Datenbanken)
    'db_dsn'  => 'mysql:host=localhost;dbname=u123456789_ruti;charset=utf8mb4',
    'db_user' => 'u123456789_ruti',
    'db_pass' => 'HIER-DAS-DATENBANK-PASSWORT',

    // Adresse der App (für Links in E-Mails)
    'app_url' => 'https://bollmer.click/ruti/app/',

    // Absender für „Passwort vergessen“ und Bestätigungs-Mails.
    // Muss ein Postfach deiner Domain sein (hPanel → E-Mails), z. B. noreply@bollmer.click
    'mail_from'      => 'noreply@bollmer.click',
    'mail_from_name' => 'Ruti',

    // Wie lange eine Anmeldung gültig bleibt (Tage, verlängert sich bei Nutzung)
    'session_days' => 180,
];
