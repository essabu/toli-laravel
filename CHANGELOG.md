# Changelog — essabu/toli-laravel

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-09-21

First release, on `essabu/toli` 0.2 (contract version 2).

### Added

- The service provider: a `Toli` client from `config/toli.php` and
  `TOLI_API_KEY`, and a `Reader` that keeps one reading per subject and model.
- The `toli_readings` migration, loaded on install and publishable. Its unique
  key on `(subject_type, subject_id, model)` is what makes "never read twice"
  a rule the database refuses to break.
- `ReadingRecorded`, a broadcast event on `private-toli.{type}` and
  `private-toli.{type}.{id}`, carrying the decision and never the state.
- `php artisan toli:kinds`: the catalogue, by category, from the terminal.
