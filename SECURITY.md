# Security policy

This package holds the keys to post as your X account, so security reports are taken seriously.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## What counts as a vulnerability

For example:

- a way to make the package post something the caller did not pass;
- a request that sends the consumer secret or the access token secret over the wire;
- keys such as `X_CONSUMER_SECRET` or `X_ACCESS_TOKEN_SECRET` ending up in logs, exceptions or output;
- a way around `allow_links` or the daily limit.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/api-x/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, the Laravel version and the steps or request that reproduce it.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.

Problems in X itself belong with X; this is an independent package.
