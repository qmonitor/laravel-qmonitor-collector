# Changelog

All notable changes to `laravel-qmonitor-collector` will be documented in this file.

## 2.0.1 - 2021-07-06
- increased release time when the heartbeat endpoint responds with a 429 status code;
- added an `uuid` to heartbeat payload to help with deduplication;
- updated event listener to report on all errors for easier debugging;
- updated the `dont_monitor` config logic to also monitor the Qmonitor speficic jobs by default;

## 2.0.0 - 2021-06-30

- initial release for collector package compatible with Laravel 7.x and 8.x

## 1.0.0 - 2021-06-30

- initial release for collector package compatible with Laravel 5.7, 5.8 and 6.x
