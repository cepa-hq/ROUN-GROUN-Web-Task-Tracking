# RG63 Coupon Studio

23 November 2026. New plugin `rg-coupon-studio` only, staging installation authorized by owner. No production or shared plugin edits.

Implementation checkpoint: PHP source built locally in five files: bootstrap, storage, engine, admin and staging QA. Native Woo coupon editor extended; existing custom cart and Checkout Studio coupon endpoints intercepted only after their nonce validation. No frontend assets or code-list APIs. Four InnoDB tables for encrypted attempt snapshots, atomic identity claims, distinct successful redemptions and rate limiting. Claims keyed by coupon/scope/identity; no raw IP storage by this plugin. All PHP files linted with PHP 8.4; deployed server PHP 8.2. Real Woo 11.3.1 hook signatures inspected via read-only MCP. Next: staging installation, native admin field verification and disposable QA fixtures with gateway/email/webhooks disabled, then browser cart/checkout tests. No actual payment or existing customer order mutation.

Known honest boundaries: browser extensions with page access can see entered coupon codes. IP identifies a connection and cannot prove a person. A payment received asynchronously after a failed order released its reservation can conflict; do not overwrite claims, put order on hold for manager review. Pending orders reserve capacity without counting as successful use. Consumed limits survive refunds. Data and private key must migrate together; deactivation does not erase records.

Issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/63
