# Changelog

All notable changes to `laravel-qmonitor-collector` will be documented in this file.

## 1.0.1 - 2021-07-06
- increased release time when the heartbeat endpoint responds with a 429 status code;
- added an `uuid` to heartbeat payload to help with deduplication;
- updated event listener to report on all errors for easier debugging;
- updated the `dont_monitor` config logic to also monitor the Qmonitor heartbeat job by default;
- fixed version in collector

## 1.0.0 - 2021-06-30
- initial release
