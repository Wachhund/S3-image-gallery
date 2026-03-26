# S3 Image Gallery

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Slim](https://img.shields.io/badge/Slim-4-74b566?logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-11-003545?logo=mariadb&logoColor=white)
![RustFS](https://img.shields.io/badge/Storage-RustFS-e6522c?logo=rust&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![WebAuthn](https://img.shields.io/badge/Auth-Passkeys%2FWebAuthn-4285F4?logo=webauthn&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

Modern image gallery with S3-compatible object storage, passkey authentication and Docker-based setup. Intended as a **demo project and reference implementation** — not for production use.

Moderne Bildergalerie mit S3-kompatibler Objektspeicherung, Passkey-Authentifizierung und Docker-basiertem Setup. Gedacht als **Demo-Projekt und Referenzimplementierung** — nicht als Produktionsanwendung.

---

## Features

- **Bildergalerie** — Verzeichnisnavigation, Thumbnail-Grid, Vollbild-Ansicht, Pagination
- **Event-Galerien** — Zweistufige Hierarchie: Jahr > Datum + Eventname
- **S3-kompatibler Speicher** — [RustFS](https://github.com/rustfs/rustfs) (Apache 2.0) statt MinIO (AGPL)
- **Passkey-Authentifizierung** — WebAuthn/FIDO2 via Einmal-Passwort-Registrierung
- **Bild-Upload** — Multi-File, MIME-Validierung, automatische Thumbnails mit EXIF-Rotation
- **Löschen** — Einzelne Bilder und ganze Galerien (inkl. S3-Cleanup)
- **Darkroom-UI** — Dunkles Design mit Amber-Akzenten, WCAG 2.1 AA, responsive
- **Docker-Compose** — Ein Befehl startet alles (PHP-FPM, Nginx, MariaDB, RustFS)
- **Test-Suite** — PHPUnit (Unit + Integration), PHPStan Level 6, PHP-CS-Fixer

## Schnellstart

```bash
git clone https://github.com/Wachhund/S3-image-gallery.git
cd S3-image-gallery

cp .env.example .env
# Optional: .env anpassen (Ports, Credentials, OTP)

docker compose up --build -d
```

Die Galerie ist unter **http://localhost:8080** erreichbar, die RustFS-Konsole unter **http://localhost:9001**.

### Testdaten laden

```bash
# Bucket scannen und Bilder katalogisieren
docker compose exec php php bin/scan.php

# Thumbnails erzeugen
docker compose exec php php bin/thumbs.php
```

### Passkey einrichten

1. In `.env` ein Einmal-Passwort setzen: `S3G_REGISTRATION_OTP=mein-geheimes-passwort`
2. Container neu erstellen: `docker compose up -d --force-recreate php`
3. Unter **/register** den Passkey mit dem OTP anlegen
4. Unter **/login** mit dem Passkey anmelden

## Architektur

```
S3-image-gallery/
├── bin/                    CLI-Tools (scan.php, thumbs.php)
├── docker/
│   ├── db/init.sql         Schema (InnoDB, utf8mb4, Foreign Keys)
│   ├── nginx/default.conf  Reverse Proxy + Security Headers + gzip
│   └── php/Dockerfile      PHP 8.4-FPM + GD, EXIF, PDO, Composer
├── public/
│   ├── index.php           Slim 4 Front Controller (alle Routes)
│   ├── css/style.css       Darkroom Design System (~15 KB)
│   └── img/placeholder.svg Platzhalter-SVG
├── src/Service/
│   ├── BucketScanner.php   S3-Bucket → DB-Katalog
│   ├── DatabaseFactory.php PDO-Verbindung aus ENV
│   ├── GalleryService.php  Galerie-Queries, Breadcrumbs, CRUD
│   ├── PasskeyService.php  WebAuthn (lbuchs/webauthn)
│   ├── S3ClientFactory.php AWS SDK S3Client mit Custom-Endpoint
│   ├── ThumbnailGenerator.php  GD Resize + EXIF Rotation
│   └── UploadService.php   Upload, MIME-Check, Thumbnail
├── templates/
│   ├── layout.php          Base-Layout (Header, Nav, Footer)
│   ├── pages/              Seiten-Templates
│   └── partials/           Wiederverwendbare Komponenten
├── tests/                  PHPUnit (Unit + Integration)
├── docker-compose.yml      4 Services + Init-Container
└── .env.example            Alle Umgebungsvariablen
```

## Stack

| Komponente | Technologie | Zweck |
|------------|-------------|-------|
| Runtime | PHP 8.4 (FPM) | Anwendungslogik |
| Framework | Slim 4 | Routing, Middleware, PSR-7 |
| Datenbank | MariaDB 11 | Metadaten, Passkeys |
| Speicher | RustFS | S3-kompatible Objektspeicherung |
| S3-Client | AWS SDK for PHP v3 | Bucket-Operationen |
| Auth | lbuchs/webauthn | Passkey-Registrierung + Login |
| Templates | slim/php-view | Server-seitiges Rendering |
| Webserver | Nginx (Alpine) | Reverse Proxy, Static Assets |
| Tests | PHPUnit 11, PHPStan 2 | Unit/Integration, Statische Analyse |

## Umgebungsvariablen

| Variable | Default | Beschreibung |
|----------|---------|-------------|
| `PHP_VERSION` | `8.4` | PHP Docker-Image Version |
| `DB_HOST` | `db` | MariaDB Hostname |
| `DB_NAME` | `s3gallery` | Datenbankname |
| `DB_USER` | `s3gallery` | Datenbankbenutzer |
| `DB_PASSWORD` | `changeme` | Datenbankpasswort |
| `S3_ENDPOINT` | `http://storage:9000` | RustFS API-Endpoint |
| `S3_ACCESS_KEY` | `rustfsadmin` | RustFS Zugangsschlüssel |
| `S3_SECRET_KEY` | `rustfsadmin` | RustFS Geheimschlüssel |
| `S3_BUCKET` | `gallery` | Bucket-Name |
| `NGINX_PORT` | `8080` | Galerie-Port auf dem Host |
| `RUSTFS_CONSOLE_PORT` | `9001` | RustFS-Konsole auf dem Host |
| `APP_DEBUG` | `false` | Stack-Traces anzeigen |
| `S3G_REGISTRATION_OTP` | *(leer)* | Einmal-Passwort für Passkey-Registrierung |
| `S3G_RP_ID` | `localhost` | WebAuthn Relying Party ID |

## Qualitätssicherung

```bash
# Unit-Tests
docker compose exec php composer test

# Statische Analyse (PHPStan Level 6)
docker compose exec php composer analyse

# Code-Style (PHP-CS-Fixer)
docker compose exec php composer cs-check
```

### Audit Score (2026-03-26)

| Dimension | Score |
|-----------|-------|
| Architecture | 7.0 |
| Backend | 7.5 |
| Database | 7.5 |
| Frontend | 7.5 |
| Infrastructure | 7.0 |
| Security | 7.5 |
| **Durchschnitt** | **7.3 / 10** |

## Lizenz

MIT
