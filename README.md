# GardenLawn MailSync Module

This module provides IMAP synchronization and SMTP sending capabilities for Magento 2, integrating email communication directly into the Admin Panel with an Outlook-like interface.

## Features

*   **IMAP Synchronization**: Syncs emails from configured IMAP folders (e.g., OVH).
*   **SMTP Sending**: Sends emails via SMTP and appends sent messages to the IMAP Sent folder.
*   **Outlook-like Interface**: A modern, 3-column layout (Folders, List, Preview) built with Alpine.js and Tailwind CSS.
*   **Attachments**: Supports downloading and sending attachments (stored in IMAP, proxied through Magento).
*   **System Integration**: Intercepts Magento system emails and routes them through the configured SMTP account, archiving them in the Sent folder.
*   **Async Operations**: Uses Message Queue (RabbitMQ/DB) for background synchronization after actions.

## Installation

1.  Install the module.
2.  Run `bin/magento setup:upgrade`.
3.  Configure settings in **Stores > Configuration > GardenLawn > Mail Sync**.

## Configuration

*   **Host**: IMAP/SMTP host (e.g., `pro2.mail.ovh.net`).
*   **Username/Password**: Email credentials.
*   **Ports**: IMAP (993 SSL), SMTP (587 TLS).

## Console Commands

*   `bin/magento gardenlawn:mail:sync`: Manually triggers synchronization.
    *   Options: `--folders-only` (`-f`) to sync only folder structure.
*   `bin/magento gardenlawn:mail:reset`: Clears all synced data (folders, messages, attachments) from the local database.

## Technical Details

*   **Library**: Uses `webklex/php-imap` for IMAP operations.
*   **Frontend**: Alpine.js + Tailwind CSS (v4).
*   **Database**: Stores metadata only; attachments are streamed from the mail server.
